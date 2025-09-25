# H3 Tour Management - Terraform Infrastructure
# AWS Lambda ZIP Processing Setup

terraform {
  required_version = ">= 1.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    archive = {
      source  = "hashicorp/archive"
      version = "~> 2.4"
    }
  }
}

# Variables
variable "environment" {
  description = "Environment name (dev, staging, prod)"
  type        = string
  default     = "prod"
}

variable "uploads_bucket_name" {
  description = "S3 bucket name for ZIP uploads"
  type        = string
  default     = "h3-tour-uploads"
}

variable "tours_bucket_name" {
  description = "S3 bucket name for extracted tours"
  type        = string
  default     = "h3-tour-files"
}

variable "wordpress_webhook_url" {
  description = "WordPress webhook URL for notifications"
  type        = string
  default     = "https://your-site.com/wp-json/h3/v1/tour-processed"
}

variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "us-east-1"
}

# Provider configuration
provider "aws" {
  region = var.aws_region
}

# Data sources
data "aws_caller_identity" "current" {}
data "aws_region" "current" {}

# Random suffix for unique bucket names
resource "random_id" "bucket_suffix" {
  byte_length = 4
}

# S3 Buckets
resource "aws_s3_bucket" "uploads" {
  bucket = "${var.uploads_bucket_name}-${var.environment}-${random_id.bucket_suffix.hex}"
}

resource "aws_s3_bucket" "tours" {
  bucket = "${var.tours_bucket_name}-${var.environment}-${random_id.bucket_suffix.hex}"
}

# S3 Bucket Public Access Block
resource "aws_s3_bucket_public_access_block" "uploads" {
  bucket = aws_s3_bucket.uploads.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_public_access_block" "tours" {
  bucket = aws_s3_bucket.tours.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# S3 Bucket CORS
resource "aws_s3_bucket_cors_configuration" "uploads" {
  bucket = aws_s3_bucket.uploads.id

  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["PUT", "POST"]
    allowed_origins = ["*"]
    max_age_seconds = 3000
  }
}

resource "aws_s3_bucket_cors_configuration" "tours" {
  bucket = aws_s3_bucket.tours.id

  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["GET"]
    allowed_origins = ["*"]
    max_age_seconds = 3000
  }
}

# SNS Topic for notifications
resource "aws_sns_topic" "tour_processing" {
  name         = "h3-tour-processing-${var.environment}"
  display_name = "H3 Tour Processing Notifications"
}

# Lambda Function Code Archive
data "archive_file" "lambda_zip" {
  type        = "zip"
  source_dir  = "../lambda"
  output_path = "tour_processor.zip"
  excludes = [
    "__pycache__",
    "*.pyc",
    ".DS_Store"
  ]
}

# IAM Role for Lambda
resource "aws_iam_role" "lambda_execution_role" {
  name = "h3-tour-processor-role-${var.environment}"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "lambda.amazonaws.com"
        }
      }
    ]
  })
}

# IAM Policy for Lambda S3 Access
resource "aws_iam_role_policy" "lambda_s3_policy" {
  name = "s3-access-policy"
  role = aws_iam_role.lambda_execution_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "s3:GetObject",
          "s3:DeleteObject"
        ]
        Resource = "${aws_s3_bucket.uploads.arn}/*"
      },
      {
        Effect = "Allow"
        Action = [
          "s3:PutObject",
          "s3:PutObjectAcl"
        ]
        Resource = "${aws_s3_bucket.tours.arn}/*"
      },
      {
        Effect = "Allow"
        Action = [
          "s3:ListBucket"
        ]
        Resource = [
          aws_s3_bucket.uploads.arn,
          aws_s3_bucket.tours.arn
        ]
      }
    ]
  })
}

# IAM Policy for Lambda SNS Access
resource "aws_iam_role_policy" "lambda_sns_policy" {
  name = "sns-publish-policy"
  role = aws_iam_role.lambda_execution_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "sns:Publish"
        ]
        Resource = aws_sns_topic.tour_processing.arn
      }
    ]
  })
}

# IAM Policy Attachment for Lambda Basic Execution
resource "aws_iam_role_policy_attachment" "lambda_basic_execution" {
  role       = aws_iam_role.lambda_execution_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole"
}

# Lambda Function
resource "aws_lambda_function" "tour_processor" {
  filename         = data.archive_file.lambda_zip.output_path
  function_name    = "h3-tour-processor-${var.environment}"
  description      = "Processes uploaded tour ZIP files"
  role            = aws_iam_role.lambda_execution_role.arn
  handler         = "tour_processor.lambda_handler"
  runtime         = "python3.9"
  memory_size     = 1024
  timeout         = 900  # 15 minutes
  source_code_hash = data.archive_file.lambda_zip.output_base64sha256

  environment {
    variables = {
      UPLOADS_BUCKET         = aws_s3_bucket.uploads.id
      TOURS_BUCKET          = aws_s3_bucket.tours.id
      NOTIFICATION_TOPIC_ARN = aws_sns_topic.tour_processing.arn
      WORDPRESS_WEBHOOK_URL  = var.wordpress_webhook_url
      ENVIRONMENT           = var.environment
    }
  }

  depends_on = [
    aws_iam_role_policy_attachment.lambda_basic_execution,
    aws_cloudwatch_log_group.lambda_logs
  ]
}

# CloudWatch Log Group
resource "aws_cloudwatch_log_group" "lambda_logs" {
  name              = "/aws/lambda/h3-tour-processor-${var.environment}"
  retention_in_days = 30
}

# Lambda Permission for S3 to invoke function
resource "aws_lambda_permission" "s3_invoke_lambda" {
  statement_id  = "AllowExecutionFromS3Bucket"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.tour_processor.function_name
  principal     = "s3.amazonaws.com"
  source_arn    = aws_s3_bucket.uploads.arn
}

# S3 Bucket Notification
resource "aws_s3_bucket_notification" "uploads_notification" {
  bucket = aws_s3_bucket.uploads.id

  lambda_function {
    lambda_function_arn = aws_lambda_function.tour_processor.arn
    events             = ["s3:ObjectCreated:*"]
    filter_prefix      = ""
    filter_suffix      = ".zip"
  }

  depends_on = [aws_lambda_permission.s3_invoke_lambda]
}

# CloudWatch Alarms
resource "aws_cloudwatch_metric_alarm" "lambda_errors" {
  alarm_name          = "h3-tour-processor-errors-${var.environment}"
  comparison_operator = "GreaterThanOrEqualToThreshold"
  evaluation_periods  = "2"
  metric_name        = "Errors"
  namespace          = "AWS/Lambda"
  period             = "300"
  statistic          = "Sum"
  threshold          = "1"
  alarm_description  = "This metric monitors lambda errors"
  alarm_actions      = [aws_sns_topic.tour_processing.arn]

  dimensions = {
    FunctionName = aws_lambda_function.tour_processor.function_name
  }
}

resource "aws_cloudwatch_metric_alarm" "lambda_duration" {
  alarm_name          = "h3-tour-processor-duration-${var.environment}"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "2"
  metric_name        = "Duration"
  namespace          = "AWS/Lambda"
  period             = "300"
  statistic          = "Average"
  threshold          = "600000"  # 10 minutes in milliseconds
  alarm_description  = "This metric monitors lambda duration"
  alarm_actions      = [aws_sns_topic.tour_processing.arn]

  dimensions = {
    FunctionName = aws_lambda_function.tour_processor.function_name
  }
}

# Random ID for unique naming
resource "random_id" "deployment_id" {
  byte_length = 8
}