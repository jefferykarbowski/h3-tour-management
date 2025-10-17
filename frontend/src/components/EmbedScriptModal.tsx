import React, { useState, useEffect } from 'react';

interface EmbedScriptModalProps {
  isOpen: boolean;
  onClose: () => void;
  tourName: string;
  tourUrl: string;
  embedScript: string;
  embedScriptResponsive: string;
}

export const EmbedScriptModal: React.FC<EmbedScriptModalProps> = ({
  isOpen,
  onClose,
  tourName,
  tourUrl,
  embedScript,
  embedScriptResponsive,
}) => {
  const [copiedStandard, setCopiedStandard] = useState(false);
  const [copiedResponsive, setCopiedResponsive] = useState(false);

  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('keydown', handleEscape);
    }

    return () => {
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isOpen, onClose]);

  const handleCopyToClipboard = async (text: string, type: 'standard' | 'responsive') => {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
      } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
      }

      if (type === 'standard') {
        setCopiedStandard(true);
        setTimeout(() => setCopiedStandard(false), 2000);
      } else {
        setCopiedResponsive(true);
        setTimeout(() => setCopiedResponsive(false), 2000);
      }
    } catch (err) {
      alert('Failed to copy to clipboard. Please select and copy manually.');
    }
  };

  if (!isOpen) return null;

  return (
    <div
      className={`h3tm-modal-overlay h3tm-embed-modal ${isOpen ? 'h3tm-modal-show' : ''}`}
      role="dialog"
      onClick={(e) => {
        if (e.target === e.currentTarget) {
          onClose();
        }
      }}
    >
      <div className="h3tm-modal-container h3tm-embed-container">
        <div className="h3tm-modal-header">
          <h3>Embed Code for: {tourName}</h3>
          <button
            type="button"
            className="h3tm-modal-close"
            onClick={onClose}
            style={{
              background: 'none',
              border: 'none',
              fontSize: '28px',
              cursor: 'pointer',
              color: '#666',
              padding: '0',
              width: '30px',
              height: '30px',
              lineHeight: '1',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
            onMouseEnter={(e) => e.currentTarget.style.color = '#000'}
            onMouseLeave={(e) => e.currentTarget.style.color = '#666'}
            aria-label="Close modal"
          >
            &times;
          </button>
        </div>

        <div className="h3tm-modal-body">
          <p>
            <strong>Tour URL:</strong>{' '}
            <a href={tourUrl} target="_blank" rel="noopener noreferrer">
              {tourUrl}
            </a>
          </p>

          <div className="h3tm-embed-option">
            <h4>Standard Embed (Fixed Height)</h4>
            <textarea
              id="h3tm-embed-standard"
              className="h3tm-embed-code"
              value={embedScript}
              readOnly
            />
            <button
              type="button"
              className={`button button-primary h3tm-copy-embed ${copiedStandard ? 'h3tm-copy-success' : ''}`}
              onClick={() => handleCopyToClipboard(embedScript, 'standard')}
            >
              {copiedStandard ? '✓ Copied!' : 'Copy to Clipboard'}
            </button>
          </div>

          <div className="h3tm-embed-option">
            <h4>Responsive Embed (16:9 Aspect Ratio)</h4>
            <textarea
              id="h3tm-embed-responsive"
              className="h3tm-embed-code"
              value={embedScriptResponsive}
              readOnly
            />
            <button
              type="button"
              className={`button button-primary h3tm-copy-embed ${copiedResponsive ? 'h3tm-copy-success' : ''}`}
              onClick={() => handleCopyToClipboard(embedScriptResponsive, 'responsive')}
            >
              {copiedResponsive ? '✓ Copied!' : 'Copy to Clipboard'}
            </button>
          </div>

          <div className="h3tm-embed-instructions">
            <h4>How to Use:</h4>
            <ol>
              <li>Click "Copy to Clipboard" on your preferred embed option</li>
              <li>Paste the code into your website HTML</li>
              <li>The tour will display in an iframe</li>
            </ol>
            <p>
              <strong>Note:</strong> The responsive embed maintains a 16:9 aspect ratio and works well on all devices.
            </p>
          </div>
        </div>

        <div className="h3tm-modal-footer">
          <button type="button" className="button button-secondary h3tm-modal-close" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
};
