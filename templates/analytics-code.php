<!-- Google Analytics -->
<?php if ({{ANALYTICS_ENABLED}}) : ?>
<script async src="https://www.googletagmanager.com/gtag/js?id={{GA_MEASUREMENT_ID}}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  
  // Configure GA4
  gtag('config', '{{GA_MEASUREMENT_ID}}', {
    'page_title': '{{TOUR_TITLE}}',
    'page_path': '/{{TOUR_DIR}}/{{TOUR_NAME}}/',
    'custom_map.dimension1': 'tour_name'
  });
  
  // Track tour view event
  gtag('event', 'tour_view', {
    'tour_name': '{{TOUR_NAME}}',
    'tour_title': '{{TOUR_TITLE}}',
    'event_category': 'Tour Engagement',
    'event_label': '{{TOUR_NAME}}'
  });
  
  // Enhanced tracking
  (function() {
    <?php if ({{TRACK_INTERACTIONS}}) : ?>
    // Track panorama interactions
    var interactionCount = 0;
    var lastInteraction = Date.now();
    
    // Generic interaction tracking
    document.addEventListener('click', function(e) {
      // Only track clicks within tour viewer
      if (e.target.closest('.pnlm-container, .tour-viewer, #panorama')) {
        interactionCount++;
        var timeSinceLastInteraction = Date.now() - lastInteraction;
        lastInteraction = Date.now();
        
        gtag('event', 'panorama_interaction', {
          'event_category': 'Tour Engagement',
          'event_label': '{{TOUR_NAME}}',
          'interaction_count': interactionCount,
          'time_between_interactions': Math.round(timeSinceLastInteraction / 1000)
        });
      }
    });
    
    // Track hotspot clicks if available
    if (typeof tour !== 'undefined' && tour.on) {
      tour.on('hotspot-click', function(e) {
        gtag('event', 'hotspot_click', {
          'event_category': 'Tour Navigation',
          'event_label': e.id || 'unknown',
          'tour_name': '{{TOUR_NAME}}'
        });
      });
    }
    <?php endif; ?>
    
    <?php if ({{TRACK_TIME_SPENT}}) : ?>
    // Track time spent on tour
    var startTime = Date.now();
    var isVisible = true;
    var totalVisibleTime = 0;
    var lastVisibleTime = startTime;
    
    // Track visibility changes
    document.addEventListener('visibilitychange', function() {
      if (document.hidden) {
        totalVisibleTime += Date.now() - lastVisibleTime;
        isVisible = false;
      } else {
        lastVisibleTime = Date.now();
        isVisible = true;
      }
    });
    
    // Send time tracking on page unload
    window.addEventListener('beforeunload', function() {
      if (isVisible) {
        totalVisibleTime += Date.now() - lastVisibleTime;
      }
      
      var totalTime = Math.round((Date.now() - startTime) / 1000);
      var visibleTime = Math.round(totalVisibleTime / 1000);
      
      // Use sendBeacon for reliability
      if (navigator.sendBeacon) {
        var data = new FormData();
        data.append('tour_name', '{{TOUR_NAME}}');
        data.append('total_time', totalTime);
        data.append('visible_time', visibleTime);
        navigator.sendBeacon('/wp-json/h3tm/v1/track-time', data);
      }
      
      // Also send to GA
      gtag('event', 'timing_complete', {
        'name': 'tour_session',
        'value': visibleTime,
        'event_category': 'Tour Engagement',
        'event_label': '{{TOUR_NAME}}',
        'custom_metric': totalTime
      });
    });
    
    // Track milestones
    var milestones = [30, 60, 120, 300, 600]; // seconds
    var milestonesReached = [];
    
    setInterval(function() {
      if (!isVisible) return;
      
      var currentVisibleTime = totalVisibleTime + (Date.now() - lastVisibleTime);
      var seconds = Math.floor(currentVisibleTime / 1000);
      
      milestones.forEach(function(milestone) {
        if (seconds >= milestone && milestonesReached.indexOf(milestone) === -1) {
          milestonesReached.push(milestone);
          
          gtag('event', 'engagement_milestone', {
            'event_category': 'Tour Engagement',
            'event_label': milestone + ' seconds',
            'tour_name': '{{TOUR_NAME}}',
            'value': milestone
          });
        }
      });
    }, 5000); // Check every 5 seconds
    <?php endif; ?>
    
    // Error tracking
    window.addEventListener('error', function(e) {
      if (e.filename && e.filename.indexOf('{{TOUR_NAME}}') !== -1) {
        gtag('event', 'exception', {
          'description': e.message + ' at ' + e.filename + ':' + e.lineno,
          'fatal': false,
          'error_source': 'tour_viewer'
        });
      }
    });
  })();
</script>

{{CUSTOM_CODE}}

<?php endif; ?>
<!-- End Google Analytics -->