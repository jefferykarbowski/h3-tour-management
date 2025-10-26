import { useState, useRef, useEffect } from "react";
import { motion } from "framer-motion";
import { useDropzone } from "react-dropzone";
import { Upload, FileArchive, Loader2, AlertCircle, CheckCircle2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Progress } from "@/components/ui/progress";

interface UpdateTourModalProps {
  isOpen: boolean;
  onClose: () => void;
  tourId: string;
  tourName: string;
  onUpdateComplete?: () => void;
}

const mainVariant = {
  initial: {
    x: 0,
    y: 0,
  },
  animate: {
    x: 20,
    y: -20,
    opacity: 0.9,
  },
};

const secondaryVariant = {
  initial: {
    opacity: 0,
  },
  animate: {
    opacity: 1,
  },
};

function GridPattern() {
  const columns = 41;
  const rows = 11;
  return (
    <div className="flex bg-gray-100 dark:bg-neutral-900 flex-shrink-0 flex-wrap justify-center items-center gap-x-px gap-y-px scale-105">
      {Array.from({ length: rows }).map((_, row) =>
        Array.from({ length: columns }).map((_, col) => {
          const index = row * columns + col;
          return (
            <div
              key={`${col}-${row}`}
              className={`w-10 h-10 flex flex-shrink-0 rounded-[2px] ${
                index % 2 === 0
                  ? "bg-gray-50 dark:bg-neutral-950"
                  : "bg-gray-50 dark:bg-neutral-950 shadow-[0px_0px_1px_3px_rgba(255,255,255,1)_inset] dark:shadow-[0px_0px_1px_3px_rgba(0,0,0,1)_inset]"
              }`}
            />
          );
        })
      )}
    </div>
  );
}

export function UpdateTourModal({ isOpen, onClose, tourId, tourName, onUpdateComplete }: UpdateTourModalProps) {
  const [file, setFile] = useState<File | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [progress, setProgress] = useState(0);
  const [isComplete, setIsComplete] = useState(false);
  const [processingPhase, setProcessingPhase] = useState<"uploading" | "processing">("uploading");
  const [progressMessage, setProgressMessage] = useState<string>('');
  const [processingStage, setProcessingStage] = useState<string>('');
  const fileInputRef = useRef<HTMLInputElement>(null);
  const pollingIntervalRef = useRef<NodeJS.Timeout | null>(null);

  // Cleanup polling on unmount
  useEffect(() => {
    return () => {
      stopPolling();
    };
  }, []);

  const handleFileChange = (newFiles: File[]) => {
    if (newFiles.length > 0) {
      const selectedFile = newFiles[0];
      if (selectedFile.name.endsWith('.zip')) {
        setFile(selectedFile);
      }
    }
  };

  const handleClick = () => {
    fileInputRef.current?.click();
  };

  const { getRootProps, isDragActive } = useDropzone({
    multiple: false,
    noClick: true,
    accept: {
      'application/zip': ['.zip'],
    },
    onDrop: handleFileChange,
  });

  const pollProgress = async (tourId: string) => {
    const formData = new FormData();
    formData.append('action', 'h3tm_get_update_progress');
    formData.append('tour_id', tourId);
    formData.append('nonce', (window as any).h3tm_ajax?.nonce || '');

    try {
      const response = await fetch((window as any).h3tm_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (data.success && data.data) {
        const progressData = data.data;
        const currentProgress = progressData.progress || 0;

        setProgress(currentProgress);
        setProgressMessage(progressData.message || '');
        setProcessingStage(progressData.stage || '');

        // Check if processing is complete
        if (progressData.status === 'completed' || currentProgress >= 100) {
          stopPolling();
          setProgress(100);
          setIsComplete(true);

          setTimeout(() => {
            setIsProcessing(false);
            setIsComplete(false);
            setFile(null);
            setProgress(0);
            setProgressMessage('');
            setProcessingStage('');
            onClose();
            onUpdateComplete?.();
          }, 1500);
        } else if (progressData.status === 'failed') {
          stopPolling();
          throw new Error(progressData.message || 'Tour update failed');
        }
      }
    } catch (error) {
      console.error('Error polling progress:', error);
      // Don't stop polling on transient errors - Lambda might still be processing
    }
  };

  const startPolling = (tourId: string) => {
    // Clear any existing interval
    stopPolling();

    // Poll every 2 seconds
    pollingIntervalRef.current = setInterval(() => {
      pollProgress(tourId);
    }, 2000);

    // Do an immediate poll
    pollProgress(tourId);
  };

  const stopPolling = () => {
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }
  };

  const handleUpdate = async () => {
    if (!file) return;

    setIsProcessing(true);
    setProgress(0);
    setProcessingPhase("uploading");
    setProgressMessage('');
    setProcessingStage('');

    try {
      // Step 1: Get presigned URL
      const presignedData = await requestPresignedUrlForUpdate(file, tourId);
      const s3_key = presignedData.s3_key;

      if (!s3_key) {
        throw new Error('Server did not return s3_key');
      }

      // Step 2: Upload to S3
      await uploadToS3(file, presignedData);

      // Step 3: Trigger asynchronous Lambda processing
      setProcessingPhase("processing");
      setProgress(0);
      setProgressMessage('Initializing tour update...');

      const formData = new FormData();
      formData.append("action", "h3tm_update_tour");
      formData.append("tour_id", tourId);
      formData.append("s3_key", s3_key);
      formData.append("nonce", (window as any).h3tm_ajax?.nonce || "");

      const response = await fetch((window as any).h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success && data.data?.async) {
        // New async flow - start polling for progress
        startPolling(tourId);
      } else if (data.success) {
        // Old synchronous flow (backward compatibility)
        setProgress(100);
        setIsComplete(true);

        setTimeout(() => {
          setIsProcessing(false);
          setIsComplete(false);
          setFile(null);
          setProgress(0);
          onClose();
          onUpdateComplete?.();
        }, 1500);
      } else {
        throw new Error(data.data || 'Unknown error');
      }
    } catch (error) {
      console.error("Error updating tour:", error);
      alert(`Failed to update tour: ${error instanceof Error ? error.message : 'Unknown error'}`);
      stopPolling();
      setIsProcessing(false);
      setProgress(0);
      setProgressMessage('');
      setProcessingStage('');
    }
  };

  const requestPresignedUrlForUpdate = async (file: File, tourId: string) => {
    const formData = new FormData();
    formData.append('action', 'h3tm_get_s3_presigned_url');
    formData.append('tour_id', tourId);
    formData.append('file_name', file.name);
    formData.append('file_size', file.size.toString());
    formData.append('file_type', file.type || 'application/zip');
    formData.append('is_update', 'true');
    formData.append('nonce', (window as any).h3tm_ajax?.nonce || '');

    const response = await fetch((window as any).h3tm_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const responseText = await response.text();
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (error) {
      throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}`);
    }

    if (!data.success) {
      throw new Error(data.data?.message || data.data || 'Failed to get upload URL');
    }

    return data.data;
  };

  const uploadToS3 = async (file: File, uploadData: any) => {
    return new Promise<void>((resolve, reject) => {
      const xhr = new XMLHttpRequest();

      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percentComplete = Math.round((e.loaded / e.total) * 100);
          setProgress(percentComplete);
        }
      });

      xhr.addEventListener('load', () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve();
        } else {
          reject(new Error(`S3 upload failed: ${xhr.status} ${xhr.statusText}`));
        }
      });

      xhr.addEventListener('error', () => {
        reject(new Error('Network error during upload'));
      });

      xhr.addEventListener('abort', () => {
        reject(new Error('Upload cancelled'));
      });

      xhr.open('PUT', uploadData.upload_url);
      xhr.timeout = 300000; // 5 minutes

      const contentType = file.type || 'application/zip';
      xhr.setRequestHeader('Content-Type', contentType);

      xhr.send(file);
    });
  };

  const handleClose = () => {
    if (!isProcessing) {
      setFile(null);
      setProgress(0);
      onClose();
    }
  };

  return (
    <>
      <Dialog open={isOpen && !isProcessing} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Update Tour: {tourName}</DialogTitle>
            <DialogDescription>
              Upload a new version of this tour. The current version will be archived automatically.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-4">
            {/* Disclaimer */}
            <div className="rounded-lg bg-amber-50 border border-amber-200 p-4">
              <div className="flex gap-3">
                <AlertCircle className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                <p className="text-sm font-medium text-amber-900">
                  Important: This will archive and replace your current tour
                </p>
              </div>
            </div>

          {/* File Upload */}
          <div className="space-y-2">
            <Label className="text-sm font-medium">Tour File (.zip)</Label>
            <div
              className="border-2 border-dashed rounded-lg bg-background hover:bg-accent/5 transition-colors"
              {...getRootProps()}
            >
              <motion.div
                onClick={handleClick}
                whileHover="animate"
                className="p-8 cursor-pointer w-full relative overflow-hidden"
              >
                <input
                  ref={fileInputRef}
                  id="file-upload-handle"
                  type="file"
                  accept=".zip"
                  onChange={(e) => handleFileChange(Array.from(e.target.files || []))}
                  className="hidden"
                />
                <div className="absolute inset-0 [mask-image:radial-gradient(ellipse_at_center,white,transparent)]">
                  <GridPattern />
                </div>
                <div className="flex flex-col items-center justify-center relative z-10">
                  <div className="relative w-full max-w-xl mx-auto">
                    {file ? (
                      <motion.div
                        layoutId="file-upload"
                        className="relative overflow-hidden z-40 bg-card border border-border flex flex-col items-start justify-start p-4 w-full mx-auto rounded-md shadow-sm"
                      >
                        <div className="flex justify-between w-full items-center gap-4">
                          <div className="flex items-center gap-3">
                            <FileArchive className="h-5 w-5 text-blue-500" />
                            <motion.p
                              initial={{ opacity: 0 }}
                              animate={{ opacity: 1 }}
                              className="text-sm font-medium text-foreground truncate max-w-xs"
                            >
                              {file.name}
                            </motion.p>
                          </div>
                          <motion.p
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            className="rounded-md px-2 py-1 text-xs bg-blue-500/10 text-blue-600 dark:text-blue-400"
                          >
                            {(file.size / (1024 * 1024)).toFixed(2)} MB
                          </motion.p>
                        </div>
                      </motion.div>
                    ) : (
                      <>
                        <motion.div
                          layoutId="file-upload"
                          variants={mainVariant}
                          transition={{
                            type: "spring",
                            stiffness: 300,
                            damping: 20,
                          }}
                          className="relative group-hover/file:shadow-lg z-40 bg-card border border-border flex items-center justify-center h-32 w-full max-w-[8rem] mx-auto rounded-md transition-shadow"
                        >
                          {isDragActive ? (
                            <motion.div
                              initial={{ opacity: 0 }}
                              animate={{ opacity: 1 }}
                              className="flex flex-col items-center text-blue-500"
                            >
                              <span className="text-xs mb-1">Drop it</span>
                              <Upload className="h-5 w-5" />
                            </motion.div>
                          ) : (
                            <Upload className="h-5 w-5 text-muted-foreground" />
                          )}
                        </motion.div>
                        <motion.div
                          variants={secondaryVariant}
                          className="absolute opacity-0 border-2 border-dashed border-blue-400 inset-0 z-30 bg-transparent flex items-center justify-center h-32 w-full max-w-[8rem] mx-auto rounded-md"
                        />
                      </>
                    )}
                  </div>
                  {!file && (
                    <div className="mt-4 text-center">
                      <p className="text-sm font-medium text-foreground">
                        Upload .zip file
                      </p>
                      <p className="text-xs text-muted-foreground mt-1">
                        Drag and drop or click to browse
                      </p>
                    </div>
                  )}
                </div>
              </motion.div>
            </div>
          </div>

          {/* Progress Bar */}
          {isProcessing && (
            <div className="space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Upload Progress</span>
                <span className="font-medium text-blue-600">{progress}%</span>
              </div>
              <Progress value={progress} className="h-2" />
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex gap-3">
            <Button
              onClick={handleUpdate}
              disabled={!file || isProcessing}
              className="flex-1 bg-green-600 hover:bg-green-700 text-white"
            >
              {isProcessing ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Updating...
                </>
              ) : (
                <>
                  <Upload className="mr-2 h-4 w-4" />
                  Update Tour
                </>
              )}
            </Button>
            <Button
              onClick={handleClose}
              disabled={isProcessing}
              variant="outline"
            >
              Cancel
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <Dialog open={isProcessing} onOpenChange={() => {}}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>
            {isComplete
              ? "Update Complete!"
              : processingPhase === "uploading"
              ? "Uploading to S3"
              : "Processing Update"}
          </DialogTitle>
          <DialogDescription>
            {isComplete
              ? "Your tour has been updated successfully."
              : processingPhase === "uploading"
              ? "Uploading your updated tour file to AWS S3..."
              : "Archiving current version and deploying updated tour..."}
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4 py-4">
          {isComplete ? (
            <div className="flex flex-col items-center justify-center py-6">
              <motion.div
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                transition={{ type: "spring", stiffness: 200, damping: 15 }}
              >
                <CheckCircle2 className="h-16 w-16 text-green-500" />
              </motion.div>
              <p className="mt-4 text-sm font-medium">{tourName}</p>
            </div>
          ) : (
            <>
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground">
                    {processingPhase === "uploading" ? "Upload Progress" : "Processing Progress"}
                  </span>
                  <span className="font-medium text-blue-600">{progress}%</span>
                </div>
                <Progress value={progress} className="h-2" />
              </div>
              {processingPhase === "uploading" ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin text-blue-500" />
                  <span>Uploading {file?.name}...</span>
                </div>
              ) : (
                <div className="space-y-3">
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin text-blue-500" />
                    <span>{progressMessage || 'Processing tour update...'}</span>
                  </div>
                  {processingStage && (
                    <div className="pl-6 text-xs text-blue-600 font-medium">
                      Stage: {processingStage}
                    </div>
                  )}
                  <div className="pl-6 space-y-1 text-xs text-muted-foreground">
                    <div className={processingStage === 'downloading' ? 'text-blue-600 font-medium' : ''}>• Downloading from S3</div>
                    <div className={processingStage === 'extracting' ? 'text-blue-600 font-medium' : ''}>• Extracting tour files</div>
                    <div className={processingStage === 'uploading' ? 'text-blue-600 font-medium' : ''}>• Uploading to tours directory</div>
                    <div className={processingStage === 'invalidating' ? 'text-blue-600 font-medium' : ''}>• Invalidating CloudFront cache</div>
                    <div className={processingStage === 'cleanup' ? 'text-blue-600 font-medium' : ''}>• Cleaning up temporary files</div>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </DialogContent>
    </Dialog>
    </>
  );
}
