# Terraform Outputs for H3 Tour Management Infrastructure

output "uploads_bucket_name" {
  description = "Name of the uploads S3 bucket"
  value       = aws_s3_bucket.uploads.id
}

output "uploads_bucket_arn" {
  description = "ARN of the uploads S3 bucket"
  value       = aws_s3_bucket.uploads.arn
}

output "tours_bucket_name" {
  description = "Name of the tours S3 bucket"
  value       = aws_s3_bucket.tours.id
}

output "tours_bucket_arn" {
  description = "ARN of the tours S3 bucket"
  value       = aws_s3_bucket.tours.arn
}

output "lambda_function_name" {
  description = "Name of the Lambda function"
  value       = aws_lambda_function.tour_processor.function_name
}

output "lambda_function_arn" {
  description = "ARN of the Lambda function"
  value       = aws_lambda_function.tour_processor.arn
}

output "sns_topic_arn" {
  description = "ARN of the SNS notification topic"
  value       = aws_sns_topic.tour_processing.arn
}

output "uploads_bucket_url" {
  description = "S3 URL for uploads bucket"
  value       = "https://${aws_s3_bucket.uploads.id}.s3.${data.aws_region.current.name}.amazonaws.com"
}

output "tours_bucket_url" {
  description = "S3 URL for tours bucket"
  value       = "https://${aws_s3_bucket.tours.id}.s3.${data.aws_region.current.name}.amazonaws.com"
}

output "cloudwatch_log_group" {
  description = "CloudWatch log group for Lambda function"
  value       = aws_cloudwatch_log_group.lambda_logs.name
}

output "lambda_execution_role_arn" {
  description = "ARN of the Lambda execution role"
  value       = aws_iam_role.lambda_execution_role.arn
}

output "deployment_summary" {
  description = "Summary of deployed resources"
  value = {
    environment         = var.environment
    aws_region         = data.aws_region.current.name
    account_id         = data.aws_caller_identity.current.account_id
    uploads_bucket     = aws_s3_bucket.uploads.id
    tours_bucket       = aws_s3_bucket.tours.id
    lambda_function    = aws_lambda_function.tour_processor.function_name
    notification_topic = aws_sns_topic.tour_processing.name
    deployment_id      = random_id.deployment_id.hex
  }
}