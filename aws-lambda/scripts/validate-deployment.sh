#!/bin/bash

# AWS Lambda Deployment Validation Script
# Comprehensive testing of the H3 Tour Management serverless system

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
TERRAFORM_DIR="$PROJECT_ROOT/aws-lambda/terraform"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# Test results tracking
declare -a test_results=()

# Helper function to record test results
record_test() {
    local test_name="$1"
    local result="$2"
    local message="$3"

    test_results+=("$test_name:$result:$message")

    if [[ "$result" == "PASS" ]]; then
        log_success "$test_name: $message"
    elif [[ "$result" == "WARN" ]]; then
        log_warning "$test_name: $message"
    else
        log_error "$test_name: $message"
    fi
}

# Get Terraform outputs
get_terraform_outputs() {
    cd "$TERRAFORM_DIR"

    if [[ ! -f "terraform.tfstate" ]]; then
        record_test "Infrastructure Check" "FAIL" "No Terraform state found - run deployment first"
        return 1
    fi

    LAMBDA_FUNCTION_NAME=$(terraform output -raw lambda_function_name 2>/dev/null || echo "")
    LAMBDA_FUNCTION_ARN=$(terraform output -raw lambda_function_arn 2>/dev/null || echo "")
    SNS_TOPIC_ARN=$(terraform output -raw sns_topic_arn 2>/dev/null || echo "")
    LOG_GROUP_NAME=$(terraform output -raw cloudwatch_log_group 2>/dev/null || echo "")

    BUCKET_NAME=$(grep '^bucket_name' terraform.tfvars | cut -d'"' -f2 2>/dev/null || echo "")
    WEBHOOK_URL=$(grep '^webhook_url' terraform.tfvars | cut -d'"' -f2 2>/dev/null || echo "")

    if [[ -z "$LAMBDA_FUNCTION_NAME" || -z "$BUCKET_NAME" ]]; then
        record_test "Terraform Outputs" "FAIL" "Could not retrieve required Terraform outputs"
        return 1
    fi

    record_test "Terraform Outputs" "PASS" "All required outputs retrieved"
    return 0
}

# Test AWS credentials and connectivity
test_aws_connectivity() {
    log_info "Testing AWS connectivity..."

    # Test AWS CLI authentication
    if ! aws sts get-caller-identity &>/dev/null; then
        record_test "AWS Authentication" "FAIL" "AWS credentials not configured or invalid"
        return 1
    fi

    local account_id=$(aws sts get-caller-identity --query Account --output text)
    local user_arn=$(aws sts get-caller-identity --query Arn --output text)

    record_test "AWS Authentication" "PASS" "Connected as $user_arn"

    # Test S3 access
    if aws s3 ls "s3://$BUCKET_NAME" &>/dev/null; then
        record_test "S3 Bucket Access" "PASS" "Can access bucket $BUCKET_NAME"
    else
        record_test "S3 Bucket Access" "FAIL" "Cannot access bucket $BUCKET_NAME"
    fi

    return 0
}

# Test Lambda function
test_lambda_function() {
    log_info "Testing Lambda function..."

    if [[ -z "$LAMBDA_FUNCTION_NAME" ]]; then
        record_test "Lambda Function" "FAIL" "Lambda function name not found"
        return 1
    fi

    # Check if Lambda function exists
    if aws lambda get-function --function-name "$LAMBDA_FUNCTION_NAME" &>/dev/null; then
        record_test "Lambda Function Exists" "PASS" "Function $LAMBDA_FUNCTION_NAME found"
    else
        record_test "Lambda Function Exists" "FAIL" "Function $LAMBDA_FUNCTION_NAME not found"
        return 1
    fi

    # Get function configuration
    local config=$(aws lambda get-function-configuration --function-name "$LAMBDA_FUNCTION_NAME" 2>/dev/null)

    if [[ $? -eq 0 ]]; then
        local runtime=$(echo "$config" | jq -r .Runtime)
        local memory=$(echo "$config" | jq -r .MemorySize)
        local timeout=$(echo "$config" | jq -r .Timeout)
        local handler=$(echo "$config" | jq -r .Handler)

        record_test "Lambda Configuration" "PASS" "Runtime: $runtime, Memory: ${memory}MB, Timeout: ${timeout}s"

        # Validate configuration
        if [[ "$handler" != "index.handler" ]]; then
            record_test "Lambda Handler" "WARN" "Handler is $handler, expected index.handler"
        else
            record_test "Lambda Handler" "PASS" "Handler correctly set to index.handler"
        fi

        if [[ $memory -lt 512 ]]; then
            record_test "Lambda Memory" "WARN" "Memory is ${memory}MB, consider increasing for better performance"
        else
            record_test "Lambda Memory" "PASS" "Memory allocation is appropriate: ${memory}MB"
        fi

        if [[ $timeout -lt 300 ]]; then
            record_test "Lambda Timeout" "WARN" "Timeout is ${timeout}s, may be too low for large files"
        else
            record_test "Lambda Timeout" "PASS" "Timeout is appropriate: ${timeout}s"
        fi
    else
        record_test "Lambda Configuration" "FAIL" "Could not retrieve function configuration"
    fi

    return 0
}

# Test Lambda function invocation
test_lambda_invocation() {
    log_info "Testing Lambda function invocation..."

    # Create test payload
    local test_payload='{
        "Records": [
            {
                "eventSource": "aws:s3",
                "s3": {
                    "bucket": {"name": "'$BUCKET_NAME'"},
                    "object": {"key": "uploads/validation-test.zip"}
                }
            }
        ]
    }'

    # Invoke Lambda function
    local invoke_result=$(aws lambda invoke \
        --function-name "$LAMBDA_FUNCTION_NAME" \
        --payload "$test_payload" \
        --cli-binary-format raw-in-base64-out \
        /tmp/lambda-invoke-response.json 2>&1)

    if [[ $? -eq 0 ]]; then
        local status_code=$(echo "$invoke_result" | jq -r .StatusCode 2>/dev/null || echo "unknown")

        if [[ "$status_code" == "200" ]]; then
            record_test "Lambda Invocation" "PASS" "Function invoked successfully"

            # Check response
            if [[ -f "/tmp/lambda-invoke-response.json" ]]; then
                local response=$(cat /tmp/lambda-invoke-response.json)
                local response_status=$(echo "$response" | jq -r .statusCode 2>/dev/null || echo "unknown")

                if [[ "$response_status" == "200" || "$response_status" == "500" ]]; then
                    record_test "Lambda Response" "PASS" "Function returned valid response structure"
                else
                    record_test "Lambda Response" "WARN" "Unexpected response format"
                fi
            fi
        else
            record_test "Lambda Invocation" "WARN" "Function invocation returned status $status_code"
        fi
    else
        record_test "Lambda Invocation" "FAIL" "Could not invoke function: $invoke_result"
    fi

    # Clean up
    rm -f /tmp/lambda-invoke-response.json

    return 0
}

# Test S3 bucket configuration
test_s3_configuration() {
    log_info "Testing S3 bucket configuration..."

    # Check bucket notification
    local notifications=$(aws s3api get-bucket-notification-configuration --bucket "$BUCKET_NAME" 2>/dev/null)

    if [[ $? -eq 0 ]]; then
        if echo "$notifications" | jq -e '.LambdaConfigurations[]' &>/dev/null; then
            record_test "S3 Notifications" "PASS" "Lambda notifications configured"

            # Check notification filter
            local prefix=$(echo "$notifications" | jq -r '.LambdaConfigurations[0].Filter.Key.FilterRules[] | select(.Name=="prefix") | .Value' 2>/dev/null || echo "")
            local suffix=$(echo "$notifications" | jq -r '.LambdaConfigurations[0].Filter.Key.FilterRules[] | select(.Name=="suffix") | .Value' 2>/dev/null || echo "")

            if [[ "$prefix" == "uploads/" ]]; then
                record_test "S3 Notification Prefix" "PASS" "Correct prefix filter: uploads/"
            else
                record_test "S3 Notification Prefix" "WARN" "Unexpected prefix filter: $prefix"
            fi

            if [[ "$suffix" == ".zip" ]]; then
                record_test "S3 Notification Suffix" "PASS" "Correct suffix filter: .zip"
            else
                record_test "S3 Notification Suffix" "WARN" "Unexpected suffix filter: $suffix"
            fi
        else
            record_test "S3 Notifications" "FAIL" "No Lambda notifications found"
        fi
    else
        record_test "S3 Notifications" "FAIL" "Could not retrieve bucket notifications"
    fi

    # Check CORS configuration
    local cors=$(aws s3api get-bucket-cors --bucket "$BUCKET_NAME" 2>/dev/null)

    if [[ $? -eq 0 ]]; then
        record_test "S3 CORS" "PASS" "CORS configuration exists"
    else
        record_test "S3 CORS" "WARN" "No CORS configuration found"
    fi

    # Test bucket directory structure
    local directories=("uploads/" "tours/" "temp/" "failed/" "processed/")

    for dir in "${directories[@]}"; do
        if aws s3 ls "s3://$BUCKET_NAME/$dir" &>/dev/null; then
            record_test "S3 Directory $dir" "PASS" "Directory exists and is accessible"
        else
            record_test "S3 Directory $dir" "WARN" "Directory $dir may not exist (will be created on first use)"
        fi
    done

    return 0
}

# Test CloudWatch logs
test_cloudwatch_logs() {
    log_info "Testing CloudWatch logs..."

    if [[ -z "$LOG_GROUP_NAME" ]]; then
        record_test "CloudWatch Log Group" "FAIL" "Log group name not found"
        return 1
    fi

    # Check if log group exists
    if aws logs describe-log-groups --log-group-name-prefix "$LOG_GROUP_NAME" | jq -e '.logGroups[] | select(.logGroupName=="'$LOG_GROUP_NAME'")' &>/dev/null; then
        record_test "CloudWatch Log Group" "PASS" "Log group $LOG_GROUP_NAME exists"

        # Check recent log streams
        local streams=$(aws logs describe-log-streams --log-group-name "$LOG_GROUP_NAME" --order-by LastEventTime --descending --max-items 5 2>/dev/null)

        if [[ $? -eq 0 ]]; then
            local stream_count=$(echo "$streams" | jq '.logStreams | length' 2>/dev/null || echo "0")

            if [[ $stream_count -gt 0 ]]; then
                record_test "CloudWatch Log Streams" "PASS" "Found $stream_count recent log streams"
            else
                record_test "CloudWatch Log Streams" "WARN" "No log streams found - function may not have been invoked yet"
            fi
        fi
    else
        record_test "CloudWatch Log Group" "FAIL" "Log group $LOG_GROUP_NAME not found"
    fi

    return 0
}

# Test SNS topic
test_sns_topic() {
    log_info "Testing SNS topic..."

    if [[ -z "$SNS_TOPIC_ARN" ]]; then
        record_test "SNS Topic" "FAIL" "SNS topic ARN not found"
        return 1
    fi

    # Check if topic exists
    if aws sns get-topic-attributes --topic-arn "$SNS_TOPIC_ARN" &>/dev/null; then
        record_test "SNS Topic" "PASS" "Topic exists and is accessible"

        # Check subscriptions
        local subscriptions=$(aws sns list-subscriptions-by-topic --topic-arn "$SNS_TOPIC_ARN" 2>/dev/null)

        if [[ $? -eq 0 ]]; then
            local sub_count=$(echo "$subscriptions" | jq '.Subscriptions | length' 2>/dev/null || echo "0")

            if [[ $sub_count -gt 0 ]]; then
                record_test "SNS Subscriptions" "PASS" "Found $sub_count subscriptions"
            else
                record_test "SNS Subscriptions" "WARN" "No subscriptions configured - error alerts will not be sent"
            fi
        fi
    else
        record_test "SNS Topic" "FAIL" "Cannot access topic $SNS_TOPIC_ARN"
    fi

    return 0
}

# Test webhook endpoint
test_webhook_endpoint() {
    log_info "Testing webhook endpoint..."

    if [[ -z "$WEBHOOK_URL" ]]; then
        record_test "Webhook URL" "FAIL" "Webhook URL not configured"
        return 1
    fi

    # Test webhook connectivity
    local response=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 10 "$WEBHOOK_URL" 2>/dev/null || echo "000")

    case "$response" in
        "200"|"302"|"400")
            record_test "Webhook Connectivity" "PASS" "Webhook endpoint is accessible (HTTP $response)"
            ;;
        "404")
            record_test "Webhook Connectivity" "WARN" "Webhook endpoint returned 404 - may need WordPress activation"
            ;;
        "000")
            record_test "Webhook Connectivity" "FAIL" "Cannot connect to webhook endpoint"
            ;;
        *)
            record_test "Webhook Connectivity" "WARN" "Webhook endpoint returned HTTP $response"
            ;;
    esac

    return 0
}

# Test end-to-end with sample file
test_end_to_end() {
    log_info "Testing end-to-end processing..."

    # Create a minimal test ZIP file
    local test_dir="/tmp/h3-tour-test-$$"
    mkdir -p "$test_dir"

    echo '<html><body>Test Tour</body></html>' > "$test_dir/index.html"
    echo 'console.log("Test tour");' > "$test_dir/tour.js"

    cd "$test_dir"
    zip -r test-tour.zip index.html tour.js

    local test_file="$test_dir/test-tour.zip"
    local test_key="uploads/validation-test-$(date +%s).zip"

    if [[ -f "$test_file" ]]; then
        record_test "Test File Creation" "PASS" "Created test ZIP file"

        # Upload test file to S3
        if aws s3 cp "$test_file" "s3://$BUCKET_NAME/$test_key"; then
            record_test "Test File Upload" "PASS" "Uploaded test file to S3"

            # Wait for processing (Lambda should be triggered automatically)
            log_info "Waiting 30 seconds for Lambda processing..."
            sleep 30

            # Check if processed files exist
            local tour_name="validation-test-$(date +%s)"
            if aws s3 ls "s3://$BUCKET_NAME/tours/" | grep -q "$tour_name"; then
                record_test "End-to-End Processing" "PASS" "Test tour was processed successfully"

                # Clean up processed files
                aws s3 rm "s3://$BUCKET_NAME/tours/" --recursive --exclude "*" --include "*$tour_name*" &>/dev/null
            else
                record_test "End-to-End Processing" "WARN" "Test tour processing not confirmed (check CloudWatch logs)"
            fi

            # Clean up test file
            aws s3 rm "s3://$BUCKET_NAME/$test_key" &>/dev/null
        else
            record_test "Test File Upload" "FAIL" "Could not upload test file to S3"
        fi
    else
        record_test "Test File Creation" "FAIL" "Could not create test ZIP file"
    fi

    # Clean up
    rm -rf "$test_dir"

    return 0
}

# Generate validation report
generate_report() {
    log_info "Generating validation report..."

    echo ""
    echo "========================================="
    echo "H3 Tour Management Lambda Validation Report"
    echo "========================================="
    echo "Generated: $(date)"
    echo "Bucket: $BUCKET_NAME"
    echo "Lambda Function: $LAMBDA_FUNCTION_NAME"
    echo ""

    local passed=0
    local warnings=0
    local failed=0

    echo "Test Results:"
    echo "-------------"

    for result in "${test_results[@]}"; do
        IFS=':' read -ra parts <<< "$result"
        local test_name="${parts[0]}"
        local status="${parts[1]}"
        local message="${parts[2]}"

        case "$status" in
            "PASS")
                echo "✅ $test_name: $message"
                ((passed++))
                ;;
            "WARN")
                echo "⚠️  $test_name: $message"
                ((warnings++))
                ;;
            "FAIL")
                echo "❌ $test_name: $message"
                ((failed++))
                ;;
        esac
    done

    echo ""
    echo "Summary:"
    echo "--------"
    echo "Passed: $passed"
    echo "Warnings: $warnings"
    echo "Failed: $failed"
    echo "Total Tests: $((passed + warnings + failed))"

    if [[ $failed -eq 0 ]]; then
        if [[ $warnings -eq 0 ]]; then
            echo ""
            log_success "🎉 All tests passed! The Lambda system is ready for production use."
        else
            echo ""
            log_warning "⚠️  System is functional but has $warnings warnings to review."
        fi
    else
        echo ""
        log_error "❌ System has $failed critical issues that must be resolved before production use."
    fi

    echo ""
    echo "Next Steps:"
    echo "-----------"
    if [[ $failed -gt 0 ]]; then
        echo "1. Review and fix critical issues listed above"
        echo "2. Re-run validation: $0"
    else
        echo "1. Upload a test tour ZIP to s3://$BUCKET_NAME/uploads/"
        echo "2. Monitor processing in CloudWatch logs: $LOG_GROUP_NAME"
        echo "3. Verify extracted files appear in s3://$BUCKET_NAME/tours/"
        echo "4. Check WordPress admin for webhook notifications"
    fi

    echo ""
}

# Main validation function
main() {
    log_info "Starting H3 Tour Management Lambda validation..."

    # Check prerequisites
    if ! command -v aws &>/dev/null; then
        log_error "AWS CLI is not installed"
        exit 1
    fi

    if ! command -v jq &>/dev/null; then
        log_error "jq is not installed (required for JSON parsing)"
        exit 1
    fi

    if ! command -v curl &>/dev/null; then
        log_error "curl is not installed (required for webhook testing)"
        exit 1
    fi

    # Get configuration from Terraform
    if ! get_terraform_outputs; then
        log_error "Could not retrieve Terraform configuration"
        exit 1
    fi

    # Run all validation tests
    test_aws_connectivity
    test_lambda_function
    test_lambda_invocation
    test_s3_configuration
    test_cloudwatch_logs
    test_sns_topic
    test_webhook_endpoint
    test_end_to_end

    # Generate final report
    generate_report
}

# Run validation
main "$@"