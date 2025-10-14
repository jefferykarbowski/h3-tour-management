import { useState, useRef } from "react";
import { motion } from "framer-motion";
import { useDropzone } from "react-dropzone";
import { Upload, FileArchive, Loader2, CheckCircle2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Progress } from "@/components/ui/progress";

interface TourUploadProps {
  onUploadComplete?: (tourName: string, file: File) => void;
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

export function TourUpload({ onUploadComplete }: TourUploadProps) {
  const [tourName, setTourName] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [progress, setProgress] = useState(0);
  const [isComplete, setIsComplete] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

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

  const handleUpload = async () => {
    if (!tourName || !file) return;

    setIsProcessing(true);
    setProgress(0);

    // Simulate upload progress
    const interval = setInterval(() => {
      setProgress((prev) => {
        if (prev >= 100) {
          clearInterval(interval);
          setTimeout(() => {
            setIsComplete(true);
            setTimeout(() => {
              setIsProcessing(false);
              setIsComplete(false);
              onUploadComplete?.(tourName, file);
              setTourName("");
              setFile(null);
              setProgress(0);
            }, 1500);
          }, 300);
          return 100;
        }
        return prev + 10;
      });
    }, 200);
  };

  const isValid = tourName.trim() !== "" && file !== null;

  return (
    <div className="w-full max-w-2xl p-6 space-y-6">
      <div className="space-y-2">
        <h2 className="text-2xl font-semibold tracking-tight">Upload 3D Tour</h2>
        <p className="text-sm text-muted-foreground">
          Upload your 3D tour .zip file and provide a name for your tour
        </p>
      </div>

      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="tour-name" className="text-sm font-medium">
            Tour Name
          </Label>
          <Input
            id="tour-name"
            placeholder="Enter tour name..."
            value={tourName}
            onChange={(e) => setTourName(e.target.value)}
            className="w-full"
          />
        </div>

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

        <Button
          onClick={handleUpload}
          disabled={!isValid || isProcessing}
          className="w-full bg-blue-600 hover:bg-blue-700 text-white"
        >
          {isProcessing ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              Processing...
            </>
          ) : (
            <>
              <Upload className="mr-2 h-4 w-4" />
              Upload Tour
            </>
          )}
        </Button>
      </div>

      <Dialog open={isProcessing} onOpenChange={() => {}}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {isComplete ? "Upload Complete!" : "Processing Tour"}
            </DialogTitle>
            <DialogDescription>
              {isComplete
                ? "Your 3D tour has been uploaded successfully."
                : "Please wait while we process your 3D tour file..."}
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
                    <span className="text-muted-foreground">Progress</span>
                    <span className="font-medium text-blue-600">{progress}%</span>
                  </div>
                  <Progress value={progress} className="h-2" />
                </div>
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin text-blue-500" />
                  <span>Uploading {file?.name}...</span>
                </div>
              </>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
