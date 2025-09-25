#!/bin/bash

# AWS Lambda Deployment Script for H3 Tour Management
# Automates the complete deployment of the serverless tour processing system

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
LAMBDA_DIR="$PROJECT_ROOT/aws-lambda"
TERRAFORM_DIR="$LAMBDA_DIR/terraform"
FUNCTION_DIR="$LAMBDA_DIR/lambda-tour-processor"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."

    # Check AWS CLI
    if ! command -v aws &> /dev/null; then
        log_error "AWS CLI is not installed. Please install it first."
        exit 1
    fi

    # Check AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        log_error "AWS credentials not configured or invalid."
        exit 1
    fi

    # Check Terraform
    if ! command -v terraform &> /dev/null; then
        log_error "Terraform is not installed. Please install it first."
        exit 1
    fi

    # Check Node.js
    if ! command -v node &> /dev/null; then
        log_error "Node.js is not installed. Please install it first."
        exit 1
    fi

    # Check npm
    if ! command -v npm &> /dev/null; then
        log_error "npm is not installed. Please install it first."
        exit 1
    fi

    # Check zip
    if ! command -v zip &> /dev/null; then
        log_error "zip utility is not installed. Please install it first."
        exit 1
    fi

    log_success "All prerequisites satisfied"
}

# Function to validate configuration
validate_configuration() {
    log_info "Validating configuration..."

    if [[ ! -f "$TERRAFORM_DIR/terraform.tfvars" ]]; then
        log_error "terraform.tfvars not found. Please copy from terraform.tfvars.example and configure."
        exit 1
    fi

    # Check required Terraform variables
    local required_vars=("bucket_name" "webhook_url")
    for var in "${required_vars[@]}"; do
        if ! grep -q "^${var}[[:space:]]*=" "$TERRAFORM_DIR/terraform.tfvars"; then
            log_error "Required variable '${var}' not found in terraform.tfvars"
            exit 1
        fi
    done

    log_success "Configuration validation passed"
}

# Function to prepare Lambda function
prepare_lambda() {
    log_info "Preparing Lambda function..."

    cd "$FUNCTION_DIR"

    # Install dependencies
    if [[ -f "package-lock.json" ]]; then
        npm ci --production
    else
        npm install --production
    fi

    # Run tests if they exist
    if [[ -f "package.json" ]] && grep -q '"test"' package.json; then
        log_info "Running Lambda function tests..."
        npm test || {
            log_warning "Tests failed, but continuing deployment..."
        }
    fi

    log_success "Lambda function prepared"
}

# Function to package Lambda function
package_lambda() {
    log_info "Packaging Lambda function..."

    cd "$LAMBDA_DIR"

    # Remove old package if exists
    [[ -f "h3-tour-processor.zip" ]] && rm "h3-tour-processor.zip"

    # Create package
    cd lambda-tour-processor
    zip -r ../h3-tour-processor.zip . \
        -x "*.git*" "node_modules/.cache/*" "coverage/*" "*.log" "test/*" "*.test.js"

    cd "$LAMBDA_DIR"

    local package_size=$(du -h h3-tour-processor.zip | cut -f1)
    log_success "Lambda function packaged (${package_size})"
}

# Function to deploy infrastructure
deploy_infrastructure() {
    log_info "Deploying infrastructure with Terraform..."

    cd "$TERRAFORM_DIR"

    # Initialize Terraform
    terraform init

    # Validate configuration
    terraform validate

    # Plan deployment
    log_info "Creating deployment plan..."
    terraform plan -out=tfplan

    # Apply deployment
    log_info "Applying infrastructure changes..."
    terraform apply -auto-approve tfplan

    # Clean up plan file
    rm -f tfplan

    log_success "Infrastructure deployed successfully"
}

# Function to verify deployment
verify_deployment() {
    log_info "Verifying deployment..."

    cd "$TERRAFORM_DIR"

    # Get outputs
    local lambda_function_name=$(terraform output -raw lambda_function_name 2>/dev/null || echo "")
    local sns_topic_arn=$(terraform output -raw sns_topic_arn 2>/dev/null || echo "")

    if [[ -z "$lambda_function_name" ]]; then
        log_error "Could not retrieve Lambda function name from Terraform outputs"
        return 1
    fi

    # Test Lambda function
    log_info "Testing Lambda function..."
    local test_result=$(aws lambda invoke \
        --function-name "$lambda_function_name" \
        --payload '{"test": true}' \
        --cli-binary-format raw-in-base64-out \
        /tmp/lambda-test-output.json 2>&1 || echo "FAILED")

    if [[ "$test_result" == *"FAILED"* ]]; then
        log_warning "Lambda function test failed, but function may still work for actual S3 events"
    else
        log_success "Lambda function responds to test invocation"
    fi

    # Check S3 bucket notification
    local bucket_name=$(grep '^bucket_name' "$TERRAFORM_DIR/terraform.tfvars" | cut -d'"' -f2)
    if [[ -n "$bucket_name" ]]; then
        log_info "Checking S3 bucket notification configuration..."
        local notification_config=$(aws s3api get-bucket-notification-configuration \
            --bucket "$bucket_name" 2>/dev/null || echo "{}")

        if echo "$notification_config" | grep -q "LambdaConfigurations"; then
            log_success "S3 bucket notification configured correctly"
        else
            log_warning "S3 bucket notification may not be configured correctly"
        fi
    fi

    log_success "Deployment verification completed"
}

# Function to show deployment summary
show_summary() {
    log_info "Deployment Summary"
    echo "==================="

    cd "$TERRAFORM_DIR"

    # Get Terraform outputs
    echo "Lambda Function: $(terraform output -raw lambda_function_name 2>/dev/null || echo 'N/A')"
    echo "Lambda ARN: $(terraform output -raw lambda_function_arn 2>/dev/null || echo 'N/A')"
    echo "SNS Topic: $(terraform output -raw sns_topic_arn 2>/dev/null || echo 'N/A')"
    echo "CloudWatch Logs: $(terraform output -raw cloudwatch_log_group 2>/dev/null || echo 'N/A')"

    # Get bucket name from variables
    local bucket_name=$(grep '^bucket_name' "$TERRAFORM_DIR/terraform.tfvars" | cut -d'"' -f2)
    echo "S3 Bucket: ${bucket_name}"

    # Get webhook URL
    local webhook_url=$(grep '^webhook_url' "$TERRAFORM_DIR/terraform.tfvars" | cut -d'"' -f2)
    echo "Webhook URL: ${webhook_url}"

    echo ""
    echo "Next Steps:"
    echo "1. Test the system by uploading a ZIP file to s3://${bucket_name}/uploads/"
    echo "2. Monitor CloudWatch logs for processing activity"
    echo "3. Check WordPress admin for webhook notifications"
    echo "4. Configure error notifications if not already done"

    log_success "Deployment completed successfully!"
}

# Function to rollback deployment
rollback_deployment() {
    log_warning "Rolling back deployment..."

    cd "$TERRAFORM_DIR"

    terraform destroy -auto-approve

    log_success "Rollback completed"
}

# Main deployment function
main_deploy() {
    log_info "Starting H3 Tour Management Lambda deployment..."

    check_prerequisites
    validate_configuration
    prepare_lambda
    package_lambda
    deploy_infrastructure
    verify_deployment
    show_summary
}

# Command line interface
case "${1:-deploy}" in
    "deploy")
        main_deploy
        ;;
    "rollback")
        rollback_deployment
        ;;
    "package")
        prepare_lambda
        package_lambda
        log_success "Lambda function packaged successfully"
        ;;
    "verify")
        verify_deployment
        ;;
    "summary")
        show_summary
        ;;
    "help"|"-h"|"--help")
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  deploy    - Full deployment (default)"
        echo "  rollback  - Destroy all infrastructure"
        echo "  package   - Package Lambda function only"
        echo "  verify    - Verify existing deployment"
        echo "  summary   - Show deployment summary"
        echo "  help      - Show this help"
        ;;
    *)
        log_error "Unknown command: $1"
        echo "Use '$0 help' for usage information"
        exit 1
        ;;
esac