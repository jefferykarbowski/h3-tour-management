"""
H3 Tour Management - AWS Lambda ZIP Processor
Automatically processes uploaded tour ZIP files and extracts to S3
"""

import json
import boto3
import zipfile
import os
import logging
from urllib.parse import unquote_plus
from typing import Dict, List, Any
import mimetypes
from datetime import datetime

# Configure logging
logger = logging.getLogger()
logger.setLevel(logging.INFO)

# AWS clients
s3_client = boto3.client('s3')
sns_client = boto3.client('sns')

# Environment variables
TOURS_BUCKET = os.environ.get('TOURS_BUCKET')
UPLOADS_BUCKET = os.environ.get('UPLOADS_BUCKET')
NOTIFICATION_TOPIC_ARN = os.environ.get('NOTIFICATION_TOPIC_ARN')
WORDPRESS_WEBHOOK_URL = os.environ.get('WORDPRESS_WEBHOOK_URL')

def lambda_handler(event: Dict[str, Any], context) -> Dict[str, Any]:
    """
    Main Lambda handler for processing tour ZIP files
    """
    try:
        logger.info(f"Processing event: {json.dumps(event)}")

        results = []

        # Process each S3 event record
        for record in event.get('Records', []):
            if record.get('eventSource') == 'aws:s3':
                result = process_s3_event(record)
                results.append(result)

        # Send batch notification if successful
        if all(r['success'] for r in results):
            send_completion_notification(results)

        return {
            'statusCode': 200,
            'body': json.dumps({
                'message': 'Tour ZIP processing completed',
                'results': results
            })
        }

    except Exception as e:
        logger.error(f"Lambda execution failed: {str(e)}")

        # Send error notification
        send_error_notification(str(e), event)

        return {
            'statusCode': 500,
            'body': json.dumps({
                'error': 'Tour ZIP processing failed',
                'message': str(e)
            })
        }

def process_s3_event(record: Dict[str, Any]) -> Dict[str, Any]:
    """
    Process individual S3 event record
    """
    try:
        # Extract S3 object details
        bucket = record['s3']['bucket']['name']
        key = unquote_plus(record['s3']['object']['key'])
        size = record['s3']['object']['size']

        logger.info(f"Processing ZIP file: s3://{bucket}/{key} ({size} bytes)")

        # Validate file is ZIP
        if not key.lower().endswith('.zip'):
            raise ValueError(f"File is not a ZIP: {key}")

        # Extract ZIP contents
        extraction_result = extract_zip_to_tours(bucket, key)

        # Clean up original ZIP file
        delete_original_zip(bucket, key)

        return {
            'success': True,
            'source_file': f"s3://{bucket}/{key}",
            'extracted_files': extraction_result['files'],
            'tour_directory': extraction_result['tour_path'],
            'file_count': extraction_result['file_count']
        }

    except Exception as e:
        logger.error(f"Failed to process S3 event: {str(e)}")
        return {
            'success': False,
            'error': str(e),
            'source_file': f"s3://{bucket}/{key}" if 'key' in locals() else 'unknown'
        }

def extract_zip_to_tours(source_bucket: str, zip_key: str) -> Dict[str, Any]:
    """
    Extract ZIP file contents to tours S3 bucket
    """
    try:
        # Generate tour directory name from ZIP filename
        zip_filename = os.path.basename(zip_key)
        tour_name = os.path.splitext(zip_filename)[0]
        tour_path = f"tours/{tour_name}/"

        logger.info(f"Extracting to: s3://{TOURS_BUCKET}/{tour_path}")

        # Download ZIP file to Lambda's /tmp directory
        local_zip_path = f"/tmp/{zip_filename}"
        s3_client.download_file(source_bucket, zip_key, local_zip_path)

        extracted_files = []
        file_count = 0

        # Extract ZIP contents
        with zipfile.ZipFile(local_zip_path, 'r') as zip_ref:
            for zip_info in zip_ref.infolist():
                # Skip directories and hidden files
                if zip_info.is_dir() or zip_info.filename.startswith('.'):
                    continue

                # Read file from ZIP
                file_data = zip_ref.read(zip_info)

                # Determine S3 key for extracted file
                # Remove any leading directory paths from ZIP
                clean_filename = os.path.basename(zip_info.filename)
                s3_key = f"{tour_path}{clean_filename}"

                # Determine content type
                content_type, _ = mimetypes.guess_type(clean_filename)
                if not content_type:
                    content_type = 'application/octet-stream'

                # Upload to S3 tours bucket
                s3_client.put_object(
                    Bucket=TOURS_BUCKET,
                    Key=s3_key,
                    Body=file_data,
                    ContentType=content_type,
                    Metadata={
                        'original_zip': zip_key,
                        'extraction_time': datetime.utcnow().isoformat(),
                        'tour_name': tour_name
                    }
                )

                extracted_files.append({
                    'filename': clean_filename,
                    's3_key': s3_key,
                    'size': len(file_data),
                    'content_type': content_type
                })

                file_count += 1

                logger.info(f"Extracted: {clean_filename} -> s3://{TOURS_BUCKET}/{s3_key}")

        # Clean up local ZIP file
        os.remove(local_zip_path)

        logger.info(f"Successfully extracted {file_count} files to {tour_path}")

        return {
            'tour_path': tour_path,
            'files': extracted_files,
            'file_count': file_count
        }

    except zipfile.BadZipFile as e:
        logger.error(f"Invalid ZIP file: {zip_key}")
        raise ValueError(f"Invalid ZIP file: {zip_key}")

    except Exception as e:
        logger.error(f"ZIP extraction failed: {str(e)}")
        raise

def delete_original_zip(bucket: str, key: str) -> None:
    """
    Delete the original ZIP file after successful extraction
    """
    try:
        s3_client.delete_object(Bucket=bucket, Key=key)
        logger.info(f"Deleted original ZIP: s3://{bucket}/{key}")
    except Exception as e:
        logger.warning(f"Failed to delete original ZIP: {str(e)}")
        # Don't raise exception - extraction was successful

def send_completion_notification(results: List[Dict[str, Any]]) -> None:
    """
    Send notification to WordPress about successful processing
    """
    try:
        notification_data = {
            'event': 'tour_zip_processed',
            'timestamp': datetime.utcnow().isoformat(),
            'processed_count': len(results),
            'tours': []
        }

        for result in results:
            if result['success']:
                notification_data['tours'].append({
                    'tour_directory': result['tour_directory'],
                    'file_count': result['file_count'],
                    'files': [f['filename'] for f in result['extracted_files']]
                })

        # Send SNS notification
        if NOTIFICATION_TOPIC_ARN:
            sns_client.publish(
                TopicArn=NOTIFICATION_TOPIC_ARN,
                Message=json.dumps(notification_data),
                Subject='H3 Tour ZIP Processing Complete'
            )

        logger.info(f"Sent completion notification for {len(results)} tours")

    except Exception as e:
        logger.error(f"Failed to send completion notification: {str(e)}")

def send_error_notification(error_message: str, event_data: Dict[str, Any]) -> None:
    """
    Send error notification for failed processing
    """
    try:
        error_data = {
            'event': 'tour_zip_processing_failed',
            'timestamp': datetime.utcnow().isoformat(),
            'error': error_message,
            'event_data': event_data
        }

        # Send SNS notification
        if NOTIFICATION_TOPIC_ARN:
            sns_client.publish(
                TopicArn=NOTIFICATION_TOPIC_ARN,
                Message=json.dumps(error_data),
                Subject='H3 Tour ZIP Processing Failed'
            )

        logger.error(f"Sent error notification: {error_message}")

    except Exception as e:
        logger.error(f"Failed to send error notification: {str(e)}")