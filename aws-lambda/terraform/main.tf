# AWS Lambda Tour Processing Infrastructure
# Terraform configuration for H3 Tour Management serverless processing

terraform {
  required_version = ">= 1.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

# Variables
variable "aws_region" {
  description = "AWS region for deployment"
  type        = string
  default     = "us-west-2"
}

variable "bucket_name" {
  description = "S3 bucket name for tour files"
  type        = string
}

variable "webhook_url" {
  description = "WordPress webhook URL for completion notifications"
  type        = string
}

variable "environment" {
  description = "Environment name (dev, staging, prod)"
  type        = string
  default     = "dev"
}

variable "lambda_memory_size" {
  description = "Lambda memory allocation in MB"
  type        = number
  default     = 1024
}

variable "lambda_timeout" {
  description = "Lambda timeout in seconds"
  type        = number
  default     = 900 # 15 minutes
}

# Data sources
data "aws_caller_identity" "current" {}

# SNS Topic for error notifications
resource "aws_sns_topic" "tour_processing_alerts" {
  name = "h3-tour-processing-alerts-${var.environment}"

  tags = {
    Environment = var.environment
    Service     = "h3-tour-processing"
  }
}

resource "aws_sns_topic_subscription" "email_alerts" {
  count     = var.alert_email != "" ? 1 : 0
  topic_arn = aws_sns_topic.tour_processing_alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email
}

variable "alert_email" {
  description = "Email address for error alerts"
  type        = string
  default     = ""
}

# CloudWatch Log Group
resource "aws_cloudwatch_log_group" "lambda_logs" {
  name              = "/aws/lambda/h3-tour-processor-${var.environment}"
  retention_in_days = 14

  tags = {
    Environment = var.environment
    Service     = "h3-tour-processing"
  }
}

# IAM Role for Lambda
resource "aws_iam_role" "lambda_role" {
  name = "h3-tour-processor-lambda-role-${var.environment}"

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

  tags = {
    Environment = var.environment
    Service     = "h3-tour-processing"
  }
}

# IAM Policy for Lambda
resource "aws_iam_policy" "lambda_policy" {
  name = "h3-tour-processor-lambda-policy-${var.environment}"

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = "arn:aws:logs:${var.aws_region}:${data.aws_caller_identity.current.account_id}:*"
      },
      {
        Effect = "Allow"
        Action = [
          "s3:GetObject",
          "s3:PutObject",
          "s3:DeleteObject",
          "s3:CopyObject"
        ]
        Resource = "arn:aws:s3:::${var.bucket_name}/*"
      },
      {
        Effect = "Allow"
        Action = [
          "s3:ListBucket"
        ]
        Resource = "arn:aws:s3:::${var.bucket_name}"
      },
      {
        Effect = "Allow"
        Action = [
          "sns:Publish"
        ]
        Resource = aws_sns_topic.tour_processing_alerts.arn
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "lambda_policy_attachment" {
  role       = aws_iam_role.lambda_role.name
  policy_arn = aws_iam_policy.lambda_policy.arn
}

# Lambda Function
resource "aws_lambda_function" "tour_processor" {
  function_name = "h3-tour-processor-${var.environment}"
  role          = aws_iam_role.lambda_role.arn
  handler       = "index.handler"
  runtime       = "nodejs18.x"
  timeout       = var.lambda_timeout
  memory_size   = var.lambda_memory_size

  filename         = "../h3-tour-processor.zip"
  source_code_hash = data.archive_file.lambda_zip.output_base64sha256

  environment {
    variables = {
      BUCKET_NAME           = var.bucket_name
      WEBHOOK_URL          = var.webhook_url
      SNS_TOPIC_ARN        = aws_sns_topic.tour_processing_alerts.arn
      MAX_FILE_SIZE        = "1073741824"  # 1GB
      MAX_PROCESSING_TIME  = "840000"      # 14 minutes
      ENVIRONMENT          = var.environment
    }
  }

  depends_on = [
    aws_cloudwatch_log_group.lambda_logs
  ]

  tags = {
    Environment = var.environment
    Service     = "h3-tour-processing"
  }
}

# Package Lambda function
data "archive_file" "lambda_zip" {
  type        = "zip"
  source_dir  = "../lambda-tour-processor"
  output_path = "../h3-tour-processor.zip"
  excludes    = ["node_modules", ".git", "*.log", "coverage"]
}

# S3 Event Notification
resource "aws_s3_bucket_notification" "tour_upload_notification" {
  bucket = var.bucket_name

  lambda_function {
    lambda_function_arn = aws_lambda_function.tour_processor.arn
    events              = ["s3:ObjectCreated:*"]
    filter_prefix       = "uploads/"
    filter_suffix       = ".zip"
  }

  depends_on = [aws_lambda_permission.allow_s3_invoke]
}

# Lambda permission for S3 to invoke
resource "aws_lambda_permission" "allow_s3_invoke" {
  statement_id  = "AllowExecutionFromS3Bucket"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.tour_processor.function_name
  principal     = "s3.amazonaws.com"
  source_arn    = "arn:aws:s3:::${var.bucket_name}"
}

# CloudWatch Alarms
resource "aws_cloudwatch_metric_alarm" "lambda_errors" {
  alarm_name          = "h3-tour-processor-errors-${var.environment}"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "2"
  metric_name         = "Errors"
  namespace           = "AWS/Lambda"
  period              = "300"
  statistic           = "Sum"
  threshold           = "0"
  alarm_description   = "This metric monitors lambda errors"
  alarm_actions       = [aws_sns_topic.tour_processing_alerts.arn]

  dimensions = {
    FunctionName = aws_lambda_function.tour_processor.function_name
  }

  tags = {
    Environment = var.environment
    Service     = "h3-tour-processing"
  }
}

resource "aws_cloudwatch_metric_alarm" "lambda_duration" {
  alarm_name          = "h3-tour-processor-duration-${var.environment}"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "2"
  metric_name         = "Duration"
  namespace           = "AWS/Lambda"
  period              = "300"
  statistic           = "Average"
  threshold           = "600000"  # 10 minutes
  alarm_description   = "This metric monitors lambda duration"
  alarm_actions       = [aws_sns_topic.tour_processing_alerts.arn]

  dimensions = {
    FunctionName = aws_lambda_function.tour_processor.function_name
  }

  tags = {
    Environment = var.environment
    Service     = "h3-tour-processing"
  }
}

# Outputs
output "lambda_function_arn" {
  description = "ARN of the Lambda function"
  value       = aws_lambda_function.tour_processor.arn
}

output "lambda_function_name" {
  description = "Name of the Lambda function"
  value       = aws_lambda_function.tour_processor.function_name
}

output "sns_topic_arn" {
  description = "ARN of the SNS topic for alerts"
  value       = aws_sns_topic.tour_processing_alerts.arn
}

output "cloudwatch_log_group" {
  description = "CloudWatch log group for Lambda function"
  value       = aws_cloudwatch_log_group.lambda_logs.name
}