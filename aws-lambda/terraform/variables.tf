# Variables for H3 Tour Management Lambda Infrastructure

variable "aws_region" {
  description = "AWS region for deployment"
  type        = string
  default     = "us-west-2"
  validation {
    condition = can(regex("^[a-z]{2}-[a-z]+-\\d$", var.aws_region))
    error_message = "AWS region must be a valid region format (e.g., us-west-2)."
  }
}

variable "bucket_name" {
  description = "S3 bucket name for tour files (must already exist)"
  type        = string
  validation {
    condition     = length(var.bucket_name) >= 3 && length(var.bucket_name) <= 63
    error_message = "Bucket name must be between 3 and 63 characters long."
  }
}

variable "webhook_url" {
  description = "WordPress webhook URL for completion notifications"
  type        = string
  validation {
    condition     = can(regex("^https://", var.webhook_url))
    error_message = "Webhook URL must use HTTPS."
  }
}

variable "environment" {
  description = "Environment name (dev, staging, prod)"
  type        = string
  default     = "dev"
  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Environment must be one of: dev, staging, prod."
  }
}

variable "lambda_memory_size" {
  description = "Lambda memory allocation in MB (affects CPU and network performance)"
  type        = number
  default     = 1024
  validation {
    condition     = var.lambda_memory_size >= 128 && var.lambda_memory_size <= 10240
    error_message = "Lambda memory size must be between 128 MB and 10,240 MB."
  }
}

variable "lambda_timeout" {
  description = "Lambda timeout in seconds (maximum 900 seconds / 15 minutes)"
  type        = number
  default     = 900
  validation {
    condition     = var.lambda_timeout >= 1 && var.lambda_timeout <= 900
    error_message = "Lambda timeout must be between 1 and 900 seconds."
  }
}

variable "alert_email" {
  description = "Email address for error alerts (optional)"
  type        = string
  default     = ""
  validation {
    condition     = var.alert_email == "" || can(regex("^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$", var.alert_email))
    error_message = "Alert email must be a valid email address or empty string."
  }
}

variable "max_file_size" {
  description = "Maximum file size in bytes for processing"
  type        = number
  default     = 1073741824 # 1GB
  validation {
    condition     = var.max_file_size > 0 && var.max_file_size <= 5368709120 # Max 5GB
    error_message = "Maximum file size must be between 1 byte and 5GB."
  }
}

variable "log_retention_days" {
  description = "Number of days to retain CloudWatch logs"
  type        = number
  default     = 14
  validation {
    condition = contains([
      1, 3, 5, 7, 14, 30, 60, 90, 120, 150, 180, 365, 400, 545, 731, 1827, 3653
    ], var.log_retention_days)
    error_message = "Log retention days must be one of the allowed CloudWatch values."
  }
}

variable "enable_monitoring" {
  description = "Enable CloudWatch monitoring and alarms"
  type        = bool
  default     = true
}

variable "error_threshold" {
  description = "Number of errors before triggering alarm"
  type        = number
  default     = 0
}

variable "duration_threshold_seconds" {
  description = "Duration threshold in seconds for alarm"
  type        = number
  default     = 600 # 10 minutes
  validation {
    condition     = var.duration_threshold_seconds > 0 && var.duration_threshold_seconds <= 900
    error_message = "Duration threshold must be between 1 and 900 seconds."
  }
}

variable "tags" {
  description = "Additional tags for all resources"
  type        = map(string)
  default     = {}
}

# Local values for computed configurations
locals {
  common_tags = merge(var.tags, {
    Environment   = var.environment
    Service      = "h3-tour-processing"
    ManagedBy    = "terraform"
    Project      = "h3-tour-management"
  })

  lambda_environment_vars = {
    BUCKET_NAME           = var.bucket_name
    WEBHOOK_URL          = var.webhook_url
    MAX_FILE_SIZE        = tostring(var.max_file_size)
    MAX_PROCESSING_TIME  = tostring((var.lambda_timeout - 60) * 1000) # Leave 1 minute buffer
    ENVIRONMENT          = var.environment
    LOG_LEVEL           = var.environment == "prod" ? "INFO" : "DEBUG"
  }
}