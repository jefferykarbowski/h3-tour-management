"""
H3 Tour Management - WordPress Integration Handler
Handles WordPress webhook notifications and database updates
"""

import json
import boto3
import requests
import logging
from urllib.parse import urlencode
from typing import Dict, List, Any, Optional
import os
from datetime import datetime
import hashlib
import hmac

logger = logging.getLogger()

# WordPress configuration
WORDPRESS_WEBHOOK_URL = os.environ.get('WORDPRESS_WEBHOOK_URL')
WORDPRESS_API_KEY = os.environ.get('WORDPRESS_API_KEY')
WORDPRESS_SECRET = os.environ.get('WORDPRESS_SECRET')

def send_wordpress_notification(tour_data: Dict[str, Any]) -> bool:
    """
    Send notification to WordPress about processed tour
    """
    try:
        if not WORDPRESS_WEBHOOK_URL:
            logger.warning("WordPress webhook URL not configured")
            return False

        # Prepare notification payload
        payload = {
            'action': 'tour_processed',
            'timestamp': datetime.utcnow().isoformat(),
            'data': tour_data,
            'source': 'aws_lambda'
        }

        # Add authentication if configured
        headers = {
            'Content-Type': 'application/json',
            'User-Agent': 'H3-Tour-Processor/1.0'
        }

        if WORDPRESS_API_KEY:
            headers['X-API-Key'] = WORDPRESS_API_KEY

        # Add signature if secret is configured
        if WORDPRESS_SECRET:
            body_string = json.dumps(payload, sort_keys=True)
            signature = hmac.new(
                WORDPRESS_SECRET.encode(),
                body_string.encode(),
                hashlib.sha256
            ).hexdigest()
            headers['X-Signature'] = f"sha256={signature}"

        # Send request with timeout
        response = requests.post(
            WORDPRESS_WEBHOOK_URL,
            json=payload,
            headers=headers,
            timeout=30
        )

        if response.status_code == 200:
            logger.info(f"WordPress notification sent successfully: {response.status_code}")
            return True
        else:
            logger.error(f"WordPress notification failed: {response.status_code} - {response.text}")
            return False

    except requests.exceptions.Timeout:
        logger.error("WordPress notification timeout")
        return False
    except requests.exceptions.RequestException as e:
        logger.error(f"WordPress notification request failed: {str(e)}")
        return False
    except Exception as e:
        logger.error(f"WordPress notification error: {str(e)}")
        return False

def create_tour_metadata(tour_path: str, extracted_files: List[Dict[str, Any]]) -> Dict[str, Any]:
    """
    Create comprehensive metadata for the tour
    """
    try:
        total_size = sum(f.get('size', 0) for f in extracted_files)

        # Categorize files by type
        file_types = {}
        for file_info in extracted_files:
            filename = file_info.get('filename', '')
            content_type = file_info.get('content_type', 'unknown')

            category = 'other'
            if content_type.startswith('text/html'):
                category = 'html'
            elif content_type.startswith('text/css'):
                category = 'css'
            elif content_type.startswith('application/javascript') or content_type.startswith('text/javascript'):
                category = 'javascript'
            elif content_type.startswith('image/'):
                category = 'images'
            elif content_type.startswith('video/'):
                category = 'videos'
            elif content_type.startswith('audio/'):
                category = 'audio'
            elif filename.endswith('.json'):
                category = 'config'
            elif filename.endswith(('.txt', '.md')):
                category = 'documentation'

            if category not in file_types:
                file_types[category] = {'count': 0, 'size': 0}

            file_types[category]['count'] += 1
            file_types[category]['size'] += file_info.get('size', 0)

        # Find main entry point
        entry_point = None
        for file_info in extracted_files:
            filename = file_info.get('filename', '').lower()
            if filename in ['index.html', 'tour.html', 'main.html']:
                entry_point = filename
                break

        # Look for tour configuration
        tour_config = None
        for file_info in extracted_files:
            filename = file_info.get('filename', '').lower()
            if filename in ['tour.json', 'config.json', 'manifest.json']:
                tour_config = filename
                break

        metadata = {
            'tour_path': tour_path,
            'total_files': len(extracted_files),
            'total_size': total_size,
            'file_types': file_types,
            'entry_point': entry_point,
            'config_file': tour_config,
            'processed_at': datetime.utcnow().isoformat(),
            'files': extracted_files
        }

        return metadata

    except Exception as e:
        logger.error(f"Failed to create tour metadata: {str(e)}")
        return {
            'tour_path': tour_path,
            'total_files': len(extracted_files),
            'processed_at': datetime.utcnow().isoformat(),
            'error': str(e)
        }

def handle_processing_completion(results: List[Dict[str, Any]]) -> None:
    """
    Handle completion of tour processing - send notifications
    """
    try:
        successful_tours = [r for r in results if r.get('success', False)]

        if not successful_tours:
            logger.warning("No successful tour processing to notify about")
            return

        for result in successful_tours:
            # Create comprehensive metadata
            tour_metadata = create_tour_metadata(
                result.get('tour_directory', ''),
                result.get('extracted_files', [])
            )

            # Prepare WordPress notification data
            notification_data = {
                'tour_name': result.get('tour_directory', '').replace('tours/', '').rstrip('/'),
                'source_file': result.get('source_file', ''),
                'tour_path': result.get('tour_directory', ''),
                'file_count': result.get('file_count', 0),
                'metadata': tour_metadata,
                'processing_status': 'completed',
                'files': result.get('extracted_files', [])
            }

            # Send WordPress notification
            if send_wordpress_notification(notification_data):
                logger.info(f"WordPress notified for tour: {notification_data['tour_name']}")
            else:
                logger.error(f"Failed to notify WordPress for tour: {notification_data['tour_name']}")

    except Exception as e:
        logger.error(f"Failed to handle processing completion: {str(e)}")

def handle_processing_error(error_message: str, event_data: Dict[str, Any]) -> None:
    """
    Handle processing errors - send error notifications
    """
    try:
        # Extract basic info from event
        source_file = "unknown"
        if 'Records' in event_data:
            for record in event_data['Records']:
                if record.get('eventSource') == 'aws:s3':
                    bucket = record['s3']['bucket']['name']
                    key = record['s3']['object']['key']
                    source_file = f"s3://{bucket}/{key}"
                    break

        # Prepare error notification
        error_data = {
            'tour_name': 'processing_failed',
            'source_file': source_file,
            'error_message': error_message,
            'processing_status': 'failed',
            'event_data': event_data,
            'timestamp': datetime.utcnow().isoformat()
        }

        # Send WordPress error notification
        if send_wordpress_notification(error_data):
            logger.info("WordPress notified of processing error")
        else:
            logger.error("Failed to notify WordPress of processing error")

    except Exception as e:
        logger.error(f"Failed to handle processing error notification: {str(e)}")

def validate_webhook_signature(payload: str, signature: str) -> bool:
    """
    Validate webhook signature for security
    """
    if not WORDPRESS_SECRET or not signature:
        return False

    try:
        expected_signature = hmac.new(
            WORDPRESS_SECRET.encode(),
            payload.encode(),
            hashlib.sha256
        ).hexdigest()

        # Remove sha256= prefix if present
        if signature.startswith('sha256='):
            signature = signature[7:]

        return hmac.compare_digest(expected_signature, signature)

    except Exception as e:
        logger.error(f"Signature validation error: {str(e)}")
        return False

def send_batch_notification(tours: List[Dict[str, Any]]) -> bool:
    """
    Send batch notification for multiple processed tours
    """
    try:
        if not tours:
            return True

        batch_data = {
            'batch_id': hashlib.md5(
                f"{datetime.utcnow().isoformat()}-{len(tours)}".encode()
            ).hexdigest()[:16],
            'processed_count': len(tours),
            'tours': tours,
            'processing_status': 'batch_completed'
        }

        return send_wordpress_notification(batch_data)

    except Exception as e:
        logger.error(f"Batch notification failed: {str(e)}")
        return False

# WordPress REST API endpoint handlers
def get_wordpress_tour_status(tour_name: str) -> Optional[Dict[str, Any]]:
    """
    Get tour status from WordPress REST API
    """
    try:
        if not WORDPRESS_WEBHOOK_URL:
            return None

        # Construct API endpoint
        base_url = WORDPRESS_WEBHOOK_URL.replace('/tour-processed', '')
        api_url = f"{base_url}/tour-status/{tour_name}"

        headers = {}
        if WORDPRESS_API_KEY:
            headers['X-API-Key'] = WORDPRESS_API_KEY

        response = requests.get(api_url, headers=headers, timeout=10)

        if response.status_code == 200:
            return response.json()
        else:
            logger.warning(f"WordPress API returned {response.status_code} for tour {tour_name}")
            return None

    except Exception as e:
        logger.error(f"Failed to get WordPress tour status: {str(e)}")
        return None

def update_wordpress_tour_status(tour_name: str, status: Dict[str, Any]) -> bool:
    """
    Update tour status in WordPress
    """
    try:
        if not WORDPRESS_WEBHOOK_URL:
            return False

        # Construct API endpoint
        base_url = WORDPRESS_WEBHOOK_URL.replace('/tour-processed', '')
        api_url = f"{base_url}/tour-status/{tour_name}"

        headers = {'Content-Type': 'application/json'}
        if WORDPRESS_API_KEY:
            headers['X-API-Key'] = WORDPRESS_API_KEY

        response = requests.put(
            api_url,
            json=status,
            headers=headers,
            timeout=10
        )

        return response.status_code in [200, 201]

    except Exception as e:
        logger.error(f"Failed to update WordPress tour status: {str(e)}")
        return False