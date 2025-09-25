#!/bin/bash

# H3 Tour Management - AWS Infrastructure Deployment Script
# Deploy Lambda ZIP processing infrastructure

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LAMBDA_DIR="$PROJECT_ROOT/lambda"
CF_TEMPLATE="$PROJECT_ROOT/cloudformation/tour-processor-stack.yaml"
TERRAFORM_DIR="$PROJECT_ROOT/terraform"

# Default values
ENVIRONMENT="${ENVIRONMENT:-prod}"
AWS_REGION="${AWS_REGION:-us-east-1}"
STACK_NAME="h3-tour-processor-${ENVIRONMENT}"
UPLOADS_BUCKET="${UPLOADS_BUCKET:-h3-tour-uploads}"
TOURS_BUCKET="${TOURS_BUCKET:-h3-tour-files}"
WORDPRESS_WEBHOOK="${WORDPRESS_WEBHOOK:-https://your-site.com/wp-json/h3/v1/tour-processed}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
log() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

show_help() {
    cat << EOF
H3 Tour Management - AWS Infrastructure Deployment

Usage: $0 [OPTIONS] COMMAND

COMMANDS:
  deploy-cf      Deploy using CloudFormation
  deploy-tf      Deploy using Terraform
  update-lambda  Update Lambda function code only
  destroy-cf     Destroy CloudFormation stack
  destroy-tf     Destroy Terraform infrastructure
  package        Package Lambda function for deployment
  validate       Validate CloudFormation template
  help           Show this help message

OPTIONS:
  -e, --environment ENV    Environment (dev/staging/prod) [default: prod]
  -r, --region REGION      AWS region [default: us-east-1]
  -u, --uploads-bucket     Uploads bucket name [default: h3-tour-uploads]
  -t, --tours-bucket       Tours bucket name [default: h3-tour-files]
  -w, --webhook-url        WordPress webhook URL
  -h, --help              Show help

EXAMPLES:
  # Deploy to production using CloudFormation
  $0 deploy-cf

  # Deploy to staging using Terraform
  $0 -e staging deploy-tf

  # Update Lambda code only
  $0 update-lambda

  # Deploy to different region
  $0 -r us-west-2 deploy-cf

ENVIRONMENT VARIABLES:
  AWS_PROFILE              AWS CLI profile to use
  AWS_REGION              Default AWS region
  ENVIRONMENT             Default environment name
  UPLOADS_BUCKET          Default uploads bucket name
  TOURS_BUCKET            Default tours bucket name
  WORDPRESS_WEBHOOK       Default WordPress webhook URL

EOF
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."

    # Check AWS CLI
    if ! command -v aws &> /dev/null; then
        error "AWS CLI is not installed"
        exit 1
    fi

    # Check AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        error "AWS credentials not configured"
        exit 1
    fi

    # Check Python 3
    if ! command -v python3 &> /dev/null; then
        error "Python 3 is not installed"
        exit 1
    fi

    success "Prerequisites check passed"
}

# Package Lambda function
package_lambda() {
    log "Packaging Lambda function..."

    local temp_dir=$(mktemp -d)
    local zip_file="$temp_dir/tour-processor.zip"

    # Copy Lambda files
    cp "$LAMBDA_DIR"/*.py "$temp_dir/"

    # Install dependencies if requirements.txt exists
    if [[ -f "$LAMBDA_DIR/requirements.txt" ]]; then
        log "Installing Python dependencies..."
        pip3 install -r "$LAMBDA_DIR/requirements.txt" -t "$temp_dir/" --quiet
    fi

    # Create ZIP file
    cd "$temp_dir"
    zip -r "$zip_file" . -q
    cd - > /dev/null

    # Move ZIP to project root
    mv "$zip_file" "$PROJECT_ROOT/tour-processor.zip"

    # Cleanup
    rm -rf "$temp_dir"

    success "Lambda function packaged: $PROJECT_ROOT/tour-processor.zip"
}

# Deploy using CloudFormation
deploy_cloudformation() {
    log "Deploying infrastructure using CloudFormation..."

    # Package Lambda function
    package_lambda

    # Check if stack exists
    if aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" &> /dev/null; then
        log "Updating existing stack: $STACK_NAME"
        aws cloudformation update-stack \
            --stack-name "$STACK_NAME" \
            --template-body "file://$CF_TEMPLATE" \
            --region "$AWS_REGION" \
            --capabilities CAPABILITY_NAMED_IAM \
            --parameters \
                ParameterKey=UploadsBucketName,ParameterValue="$UPLOADS_BUCKET" \
                ParameterKey=ToursBucketName,ParameterValue="$TOURS_BUCKET" \
                ParameterKey=WordPressWebhookUrl,ParameterValue="$WORDPRESS_WEBHOOK" \
                ParameterKey=Environment,ParameterValue="$ENVIRONMENT"

        aws cloudformation wait stack-update-complete --stack-name "$STACK_NAME" --region "$AWS_REGION"
    else
        log "Creating new stack: $STACK_NAME"
        aws cloudformation create-stack \
            --stack-name "$STACK_NAME" \
            --template-body "file://$CF_TEMPLATE" \
            --region "$AWS_REGION" \
            --capabilities CAPABILITY_NAMED_IAM \
            --parameters \
                ParameterKey=UploadsBucketName,ParameterValue="$UPLOADS_BUCKET" \
                ParameterKey=ToursBucketName,ParameterValue="$TOURS_BUCKET" \
                ParameterKey=WordPressWebhookUrl,ParameterValue="$WORDPRESS_WEBHOOK" \
                ParameterKey=Environment,ParameterValue="$ENVIRONMENT"

        aws cloudformation wait stack-create-complete --stack-name "$STACK_NAME" --region "$AWS_REGION"
    fi

    # Update Lambda function code
    update_lambda_code_cf

    # Get stack outputs
    log "Deployment completed! Stack outputs:"
    aws cloudformation describe-stacks \
        --stack-name "$STACK_NAME" \
        --region "$AWS_REGION" \
        --query 'Stacks[0].Outputs[*].[OutputKey,OutputValue]' \
        --output table

    success "CloudFormation deployment completed successfully"
}

# Deploy using Terraform
deploy_terraform() {
    log "Deploying infrastructure using Terraform..."

    cd "$TERRAFORM_DIR"

    # Initialize Terraform
    log "Initializing Terraform..."
    terraform init

    # Plan deployment
    log "Planning Terraform deployment..."
    terraform plan \
        -var "environment=$ENVIRONMENT" \
        -var "aws_region=$AWS_REGION" \
        -var "uploads_bucket_name=$UPLOADS_BUCKET" \
        -var "tours_bucket_name=$TOURS_BUCKET" \
        -var "wordpress_webhook_url=$WORDPRESS_WEBHOOK"

    # Apply deployment
    log "Applying Terraform configuration..."
    terraform apply -auto-approve \
        -var "environment=$ENVIRONMENT" \
        -var "aws_region=$AWS_REGION" \
        -var "uploads_bucket_name=$UPLOADS_BUCKET" \
        -var "tours_bucket_name=$TOURS_BUCKET" \
        -var "wordpress_webhook_url=$WORDPRESS_WEBHOOK"

    # Show outputs
    log "Deployment completed! Terraform outputs:"
    terraform output

    cd - > /dev/null

    success "Terraform deployment completed successfully"
}

# Update Lambda code for CloudFormation deployment
update_lambda_code_cf() {
    log "Updating Lambda function code..."

    # Get function name from stack
    local function_name=$(aws cloudformation describe-stacks \
        --stack-name "$STACK_NAME" \
        --region "$AWS_REGION" \
        --query 'Stacks[0].Outputs[?OutputKey==`TourProcessorFunctionArn`].OutputValue' \
        --output text | cut -d: -f7)

    if [[ -z "$function_name" ]]; then
        error "Could not find Lambda function name from stack outputs"
        return 1
    fi

    # Update function code
    aws lambda update-function-code \
        --function-name "$function_name" \
        --zip-file "fileb://$PROJECT_ROOT/tour-processor.zip" \
        --region "$AWS_REGION" > /dev/null

    success "Lambda function code updated: $function_name"
}

# Update Lambda code only
update_lambda() {
    log "Updating Lambda function code only..."

    # Package Lambda function
    package_lambda

    # Update based on deployment method
    if aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" &> /dev/null; then
        update_lambda_code_cf
    else
        # Try Terraform
        cd "$TERRAFORM_DIR" 2>/dev/null || {
            error "No CloudFormation stack or Terraform state found"
            exit 1
        }

        local function_name=$(terraform output -raw lambda_function_name 2>/dev/null || echo "")

        if [[ -n "$function_name" ]]; then
            aws lambda update-function-code \
                --function-name "$function_name" \
                --zip-file "fileb://$PROJECT_ROOT/tour-processor.zip" \
                --region "$AWS_REGION" > /dev/null

            success "Lambda function code updated: $function_name"
        else
            error "Could not find deployed Lambda function"
            exit 1
        fi

        cd - > /dev/null
    fi
}

# Destroy CloudFormation stack
destroy_cloudformation() {
    warn "This will destroy all resources in the CloudFormation stack: $STACK_NAME"
    read -p "Are you sure? (y/N): " -n 1 -r
    echo

    if [[ $REPLY =~ ^[Yy]$ ]]; then
        log "Destroying CloudFormation stack: $STACK_NAME"

        aws cloudformation delete-stack \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION"

        aws cloudformation wait stack-delete-complete --stack-name "$STACK_NAME" --region "$AWS_REGION"

        success "CloudFormation stack destroyed successfully"
    else
        log "Operation cancelled"
    fi
}

# Destroy Terraform infrastructure
destroy_terraform() {
    warn "This will destroy all resources managed by Terraform"
    read -p "Are you sure? (y/N): " -n 1 -r
    echo

    if [[ $REPLY =~ ^[Yy]$ ]]; then
        log "Destroying Terraform infrastructure..."

        cd "$TERRAFORM_DIR"

        terraform destroy -auto-approve \
            -var "environment=$ENVIRONMENT" \
            -var "aws_region=$AWS_REGION" \
            -var "uploads_bucket_name=$UPLOADS_BUCKET" \
            -var "tours_bucket_name=$TOURS_BUCKET" \
            -var "wordpress_webhook_url=$WORDPRESS_WEBHOOK"

        cd - > /dev/null

        success "Terraform infrastructure destroyed successfully"
    else
        log "Operation cancelled"
    fi
}

# Validate CloudFormation template
validate_template() {
    log "Validating CloudFormation template..."

    aws cloudformation validate-template \
        --template-body "file://$CF_TEMPLATE" \
        --region "$AWS_REGION"

    success "CloudFormation template is valid"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -e|--environment)
            ENVIRONMENT="$2"
            shift 2
            ;;
        -r|--region)
            AWS_REGION="$2"
            shift 2
            ;;
        -u|--uploads-bucket)
            UPLOADS_BUCKET="$2"
            shift 2
            ;;
        -t|--tours-bucket)
            TOURS_BUCKET="$2"
            shift 2
            ;;
        -w|--webhook-url)
            WORDPRESS_WEBHOOK="$2"
            shift 2
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        deploy-cf)
            COMMAND="deploy-cf"
            shift
            ;;
        deploy-tf)
            COMMAND="deploy-tf"
            shift
            ;;
        update-lambda)
            COMMAND="update-lambda"
            shift
            ;;
        destroy-cf)
            COMMAND="destroy-cf"
            shift
            ;;
        destroy-tf)
            COMMAND="destroy-tf"
            shift
            ;;
        package)
            COMMAND="package"
            shift
            ;;
        validate)
            COMMAND="validate"
            shift
            ;;
        help)
            show_help
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Update stack name with environment
STACK_NAME="h3-tour-processor-${ENVIRONMENT}"

# Show configuration
log "Configuration:"
log "  Environment: $ENVIRONMENT"
log "  AWS Region: $AWS_REGION"
log "  Stack Name: $STACK_NAME"
log "  Uploads Bucket: $UPLOADS_BUCKET"
log "  Tours Bucket: $TOURS_BUCKET"
log "  WordPress Webhook: $WORDPRESS_WEBHOOK"
echo

# Check prerequisites
check_prerequisites

# Execute command
case "${COMMAND:-}" in
    deploy-cf)
        deploy_cloudformation
        ;;
    deploy-tf)
        deploy_terraform
        ;;
    update-lambda)
        update_lambda
        ;;
    destroy-cf)
        destroy_cloudformation
        ;;
    destroy-tf)
        destroy_terraform
        ;;
    package)
        package_lambda
        ;;
    validate)
        validate_template
        ;;
    *)
        error "No command specified"
        show_help
        exit 1
        ;;
esac