#!/bin/bash

# H3 Tour Management - Deployment Testing Script
# Test the Lambda ZIP processing functionality

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
TEST_FILES_DIR="$PROJECT_ROOT/test-files"

# Default values
ENVIRONMENT="${ENVIRONMENT:-prod}"
AWS_REGION="${AWS_REGION:-us-east-1}"
STACK_NAME="h3-tour-processor-${ENVIRONMENT}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Functions
log() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Get deployment info
get_deployment_info() {
    log "Getting deployment information..."

    # Try CloudFormation first
    if aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" &> /dev/null; then
        UPLOADS_BUCKET=$(aws cloudformation describe-stacks \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION" \
            --query 'Stacks[0].Outputs[?OutputKey==`UploadsBucketName`].OutputValue' \
            --output text)

        TOURS_BUCKET=$(aws cloudformation describe-stacks \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION" \
            --query 'Stacks[0].Outputs[?OutputKey==`ToursBucketName`].OutputValue' \
            --output text)

        LAMBDA_FUNCTION=$(aws cloudformation describe-stacks \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION" \
            --query 'Stacks[0].Outputs[?OutputKey==`TourProcessorFunctionArn`].OutputValue' \
            --output text | cut -d: -f7)

        SNS_TOPIC=$(aws cloudformation describe-stacks \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION" \
            --query 'Stacks[0].Outputs[?OutputKey==`NotificationTopicArn`].OutputValue' \
            --output text)
    else
        # Try Terraform
        cd "$PROJECT_ROOT/terraform" 2>/dev/null || {
            error "No deployment found (CloudFormation or Terraform)"
            exit 1
        }

        UPLOADS_BUCKET=$(terraform output -raw uploads_bucket_name 2>/dev/null || echo "")
        TOURS_BUCKET=$(terraform output -raw tours_bucket_name 2>/dev/null || echo "")
        LAMBDA_FUNCTION=$(terraform output -raw lambda_function_name 2>/dev/null || echo "")
        SNS_TOPIC=$(terraform output -raw sns_topic_arn 2>/dev/null || echo "")

        cd - > /dev/null
    fi

    if [[ -z "$UPLOADS_BUCKET" || -z "$TOURS_BUCKET" || -z "$LAMBDA_FUNCTION" ]]; then
        error "Could not retrieve deployment information"
        exit 1
    fi

    log "Deployment info retrieved:"
    log "  Uploads Bucket: $UPLOADS_BUCKET"
    log "  Tours Bucket: $TOURS_BUCKET"
    log "  Lambda Function: $LAMBDA_FUNCTION"
    log "  SNS Topic: $SNS_TOPIC"
}

# Create test files
create_test_files() {
    log "Creating test files..."

    mkdir -p "$TEST_FILES_DIR"

    # Create test tour files
    cat > "$TEST_FILES_DIR/index.html" << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>Test Tour</title>
</head>
<body>
    <h1>Welcome to Test Tour</h1>
    <p>This is a test tour for H3 Tour Management.</p>
</body>
</html>
EOF

    cat > "$TEST_FILES_DIR/tour.json" << 'EOF'
{
    "name": "Test Tour",
    "description": "A test tour for validation",
    "version": "1.0.0",
    "files": [
        "index.html",
        "style.css",
        "script.js"
    ]
}
EOF

    cat > "$TEST_FILES_DIR/style.css" << 'EOF'
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
}

h1 {
    color: #333;
}
EOF

    cat > "$TEST_FILES_DIR/script.js" << 'EOF'
// Test tour JavaScript
console.log('Test tour loaded successfully');

window.addEventListener('load', function() {
    console.log('Tour is ready');
});
EOF

    # Create ZIP file
    cd "$TEST_FILES_DIR"
    zip -r "test-tour-$(date +%s).zip" *.html *.json *.css *.js
    cd - > /dev/null

    TEST_ZIP=$(ls "$TEST_FILES_DIR"/*.zip | head -n1)

    success "Test files created: $TEST_ZIP"
}

# Test S3 upload
test_s3_upload() {
    log "Testing S3 upload..."

    local zip_file=$(basename "$TEST_ZIP")
    local s3_key="test-uploads/$zip_file"

    # Upload to S3
    aws s3 cp "$TEST_ZIP" "s3://$UPLOADS_BUCKET/$s3_key" --region "$AWS_REGION"

    # Wait a moment for the upload to complete
    sleep 2

    # Verify upload
    if aws s3 ls "s3://$UPLOADS_BUCKET/$s3_key" --region "$AWS_REGION" > /dev/null; then
        success "File uploaded successfully: s3://$UPLOADS_BUCKET/$s3_key"
        echo "$s3_key"
    else
        error "File upload failed"
        exit 1
    fi
}

# Monitor Lambda execution
monitor_lambda() {
    local s3_key="$1"
    local max_wait=300  # 5 minutes
    local wait_time=0

    log "Monitoring Lambda function execution..."

    while [[ $wait_time -lt $max_wait ]]; do
        # Check CloudWatch logs
        local log_streams=$(aws logs describe-log-streams \
            --log-group-name "/aws/lambda/$LAMBDA_FUNCTION" \
            --region "$AWS_REGION" \
            --order-by LastEventTime \
            --descending \
            --max-items 5 \
            --query 'logStreams[*].logStreamName' \
            --output text)

        if [[ -n "$log_streams" ]]; then
            local latest_stream=$(echo "$log_streams" | head -n1)

            # Get recent log events
            local recent_logs=$(aws logs get-log-events \
                --log-group-name "/aws/lambda/$LAMBDA_FUNCTION" \
                --log-stream-name "$latest_stream" \
                --region "$AWS_REGION" \
                --start-time $(($(date +%s) * 1000 - 300000)) \
                --query 'events[*].message' \
                --output text)

            if echo "$recent_logs" | grep -q "Processing ZIP file.*$s3_key"; then
                log "Lambda execution detected for uploaded file"

                # Wait for completion
                sleep 10

                # Check for completion or error
                if echo "$recent_logs" | grep -q "Tour ZIP processing completed"; then
                    success "Lambda processing completed successfully"
                    return 0
                elif echo "$recent_logs" | grep -q "ERROR"; then
                    error "Lambda processing failed"
                    echo "$recent_logs" | grep "ERROR"
                    return 1
                fi
            fi
        fi

        sleep 5
        wait_time=$((wait_time + 5))
        echo -n "."
    done

    echo
    warn "Lambda monitoring timeout reached"
    return 1
}

# Verify extraction
verify_extraction() {
    log "Verifying tour extraction..."

    local zip_name=$(basename "$TEST_ZIP" .zip)
    local expected_files=("index.html" "tour.json" "style.css" "script.js")

    log "Checking for extracted files in tours/$zip_name/..."

    local all_found=true

    for file in "${expected_files[@]}"; do
        local s3_key="tours/$zip_name/$file"

        if aws s3 ls "s3://$TOURS_BUCKET/$s3_key" --region "$AWS_REGION" > /dev/null; then
            success "Found: $file"
        else
            error "Missing: $file"
            all_found=false
        fi
    done

    if [[ "$all_found" == true ]]; then
        success "All files extracted successfully"

        # Download and verify content of one file
        local temp_file=$(mktemp)
        aws s3 cp "s3://$TOURS_BUCKET/tours/$zip_name/index.html" "$temp_file" --region "$AWS_REGION"

        if grep -q "Welcome to Test Tour" "$temp_file"; then
            success "File content verified"
        else
            error "File content verification failed"
        fi

        rm -f "$temp_file"
        return 0
    else
        error "Extraction verification failed"
        return 1
    fi
}

# Test Lambda function directly
test_lambda_direct() {
    log "Testing Lambda function directly..."

    local test_event="{
        \"Records\": [
            {
                \"eventSource\": \"aws:s3\",
                \"s3\": {
                    \"bucket\": {
                        \"name\": \"$UPLOADS_BUCKET\"
                    },
                    \"object\": {
                        \"key\": \"test-direct/$(basename "$TEST_ZIP")\",
                        \"size\": $(stat -f%z "$TEST_ZIP" 2>/dev/null || stat -c%s "$TEST_ZIP")
                    }
                }
            }
        ]
    }"

    # Upload test file
    aws s3 cp "$TEST_ZIP" "s3://$UPLOADS_BUCKET/test-direct/$(basename "$TEST_ZIP")" --region "$AWS_REGION"

    # Invoke Lambda function
    local response=$(aws lambda invoke \
        --function-name "$LAMBDA_FUNCTION" \
        --payload "$test_event" \
        --region "$AWS_REGION" \
        /tmp/lambda-response.json)

    local status_code=$(echo "$response" | jq -r '.StatusCode')
    local function_error=$(echo "$response" | jq -r '.FunctionError // empty')

    if [[ "$status_code" == "200" && -z "$function_error" ]]; then
        success "Lambda function invocation successful"
        cat /tmp/lambda-response.json | jq .
        return 0
    else
        error "Lambda function invocation failed"
        echo "Status Code: $status_code"
        echo "Function Error: $function_error"
        cat /tmp/lambda-response.json
        return 1
    fi
}

# Check CloudWatch metrics
check_metrics() {
    log "Checking CloudWatch metrics..."

    local end_time=$(date -u +%Y-%m-%dT%H:%M:%S)
    local start_time=$(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%S)

    # Check invocation count
    local invocations=$(aws cloudwatch get-metric-statistics \
        --namespace AWS/Lambda \
        --metric-name Invocations \
        --dimensions Name=FunctionName,Value="$LAMBDA_FUNCTION" \
        --start-time "$start_time" \
        --end-time "$end_time" \
        --period 3600 \
        --statistics Sum \
        --region "$AWS_REGION" \
        --query 'Datapoints[0].Sum' \
        --output text)

    # Check error count
    local errors=$(aws cloudwatch get-metric-statistics \
        --namespace AWS/Lambda \
        --metric-name Errors \
        --dimensions Name=FunctionName,Value="$LAMBDA_FUNCTION" \
        --start-time "$start_time" \
        --end-time "$end_time" \
        --period 3600 \
        --statistics Sum \
        --region "$AWS_REGION" \
        --query 'Datapoints[0].Sum' \
        --output text)

    log "Lambda metrics (last hour):"
    log "  Invocations: ${invocations:-0}"
    log "  Errors: ${errors:-0}"

    if [[ "${errors:-0}" == "0" ]]; then
        success "No errors detected in metrics"
    else
        warn "Errors detected in Lambda function"
    fi
}

# Cleanup test files
cleanup() {
    log "Cleaning up test files..."

    # Remove test files from S3
    if [[ -n "$UPLOADS_BUCKET" ]]; then
        aws s3 rm "s3://$UPLOADS_BUCKET/test-uploads/" --recursive --region "$AWS_REGION" 2>/dev/null || true
        aws s3 rm "s3://$UPLOADS_BUCKET/test-direct/" --recursive --region "$AWS_REGION" 2>/dev/null || true
    fi

    if [[ -n "$TOURS_BUCKET" ]]; then
        aws s3 rm "s3://$TOURS_BUCKET/tours/test-tour" --recursive --region "$AWS_REGION" 2>/dev/null || true
    fi

    # Remove local test files
    rm -rf "$TEST_FILES_DIR"
    rm -f /tmp/lambda-response.json

    success "Cleanup completed"
}

# Show help
show_help() {
    cat << EOF
H3 Tour Management - Deployment Testing Script

Usage: $0 [OPTIONS] [TESTS...]

OPTIONS:
  -e, --environment ENV    Environment (dev/staging/prod) [default: prod]
  -r, --region REGION      AWS region [default: us-east-1]
  -h, --help              Show help

AVAILABLE TESTS:
  all                     Run all tests (default)
  upload                  Test S3 upload only
  lambda-direct          Test Lambda function directly
  end-to-end             Test complete upload and processing flow
  metrics                Check CloudWatch metrics
  cleanup                Clean up test files only

EXAMPLES:
  # Run all tests
  $0

  # Test upload only
  $0 upload

  # Test in staging environment
  $0 -e staging

  # Multiple specific tests
  $0 upload lambda-direct

EOF
}

# Parse arguments
TESTS=()
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
        -h|--help)
            show_help
            exit 0
            ;;
        cleanup)
            TESTS+=("cleanup")
            shift
            ;;
        *)
            TESTS+=("$1")
            shift
            ;;
    esac
done

# Default to all tests if none specified
if [[ ${#TESTS[@]} -eq 0 ]]; then
    TESTS=("all")
fi

# Update stack name with environment
STACK_NAME="h3-tour-processor-${ENVIRONMENT}"

log "Starting deployment tests..."
log "Environment: $ENVIRONMENT"
log "Region: $AWS_REGION"
log "Tests: ${TESTS[*]}"

# Get deployment info
get_deployment_info

# Run tests
for test in "${TESTS[@]}"; do
    case "$test" in
        all)
            create_test_files
            s3_key=$(test_s3_upload)
            monitor_lambda "$s3_key" && verify_extraction
            test_lambda_direct
            check_metrics
            cleanup
            ;;
        upload)
            create_test_files
            test_s3_upload
            ;;
        lambda-direct)
            create_test_files
            test_lambda_direct
            ;;
        end-to-end)
            create_test_files
            s3_key=$(test_s3_upload)
            monitor_lambda "$s3_key" && verify_extraction
            ;;
        metrics)
            check_metrics
            ;;
        cleanup)
            cleanup
            ;;
        *)
            error "Unknown test: $test"
            exit 1
            ;;
    esac
done

success "All tests completed successfully!"