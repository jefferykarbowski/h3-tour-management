import { useState, useEffect } from "react";
import {
  Eye,
  Link2,
  RefreshCw,
  Pencil,
  Code,
  Trash2,
  Loader2,
  CheckCircle2,
  XCircle,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

interface Tour {
  name: string;
  url: string;
  status?: "completed" | "processing" | "uploading" | "failed";
}

interface ToursTableProps {
  onRefresh?: () => void;
}

declare global {
  interface Window {
    h3tm_ajax?: {
      ajax_url: string;
      nonce: string;
    };
  }
}

export function ToursTable({ onRefresh }: ToursTableProps) {
  const [tours, setTours] = useState<Tour[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [editingTour, setEditingTour] = useState<string | null>(null);
  const [editValue, setEditValue] = useState("");

  useEffect(() => {
    loadTours();
  }, []);

  const loadTours = async () => {
    setIsLoading(true);
    try {
      const formData = new FormData();
      formData.append("action", "h3tm_list_s3_tours");
      formData.append("nonce", window.h3tm_ajax?.nonce || "");

      const response = await fetch(window.h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();
      if (data.success && data.data) {
        const toursList = Array.isArray(data.data) ? data.data : data.data.tours || [];
        setTours(toursList.map((tour: any) => ({
          name: typeof tour === "string" ? tour : tour.name,
          url: window.location.origin + "/h3panos/" + encodeURIComponent(typeof tour === "string" ? tour : tour.name),
          status: typeof tour === "string" ? "completed" : tour.status,
        })));
      }
    } catch (error) {
      console.error("Error loading tours:", error);
    } finally {
      setIsLoading(false);
    }
  };

  const startEdit = (tourName: string) => {
    setEditingTour(tourName);
    setEditValue(tourName);
  };

  const cancelEdit = () => {
    setEditingTour(null);
    setEditValue("");
  };

  const saveEdit = async (oldName: string) => {
    if (editValue === oldName || !editValue.trim()) {
      cancelEdit();
      return;
    }

    try {
      const formData = new FormData();
      formData.append("action", "h3tm_rename_tour");
      formData.append("old_name", oldName);
      formData.append("new_name", editValue);
      formData.append("nonce", window.h3tm_ajax?.nonce || "");

      const response = await fetch(window.h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();
      if (data.success) {
        await loadTours();
        cancelEdit();
      }
    } catch (error) {
      console.error("Error renaming tour:", error);
    }
  };

  const handleAction = async (action: string, tourName: string, tourUrl?: string) => {
    switch (action) {
      case "view":
        window.open(tourUrl, "_blank");
        break;
      case "changeUrl":
        const newUrl = prompt("Enter new URL path (e.g., /custom-path/tour-name):", tourUrl);
        if (newUrl && newUrl !== tourUrl) {
          try {
            const formData = new FormData();
            formData.append("action", "h3tm_change_tour_url");
            formData.append("tour_name", tourName);
            formData.append("new_url", newUrl);
            formData.append("nonce", window.h3tm_ajax?.nonce || "");

            const response = await fetch(window.h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php", {
              method: "POST",
              body: formData,
            });

            const data = await response.json();
            if (data.success) {
              alert(`URL changed successfully for "${tourName}"`);
              await loadTours();
            } else {
              alert(`Failed to change URL: ${data.data || 'Unknown error'}`);
            }
          } catch (error) {
            console.error("Error changing URL:", error);
            alert("Failed to change URL. Check console for details.");
          }
        }
        break;
      case "update":
        if (confirm(`Re-upload and update "${tourName}"? This will replace the existing tour with a new version.`)) {
          try {
            const formData = new FormData();
            formData.append("action", "h3tm_update_tour");
            formData.append("tour_name", tourName);
            formData.append("nonce", window.h3tm_ajax?.nonce || "");

            const response = await fetch(window.h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php", {
              method: "POST",
              body: formData,
            });

            const data = await response.json();
            if (data.success) {
              alert(`Update initiated for "${tourName}". ${data.data || ''}`);
              await loadTours();
            } else {
              alert(`Failed to update: ${data.data || 'Unknown error'}`);
            }
          } catch (error) {
            console.error("Error updating tour:", error);
            alert("Failed to update tour. Check console for details.");
          }
        }
        break;
      case "rename":
        startEdit(tourName);
        break;
      case "getScript":
        try {
          const formData = new FormData();
          formData.append("action", "h3tm_get_embed_script");
          formData.append("tour_name", tourName);
          formData.append("nonce", window.h3tm_ajax?.nonce || "");

          const response = await fetch(window.h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: formData,
          });

          const data = await response.json();
          if (data.success && data.data) {
            // Create a dialog/modal to show the script
            const script = data.data.script || data.data;
            if (navigator.clipboard) {
              await navigator.clipboard.writeText(script);
              alert(`Embed script copied to clipboard!\n\nScript preview:\n${script.substring(0, 200)}...`);
            } else {
              // Fallback: show in prompt for manual copy
              prompt("Copy the embed script below:", script);
            }
          } else {
            alert(`Failed to get script: ${data.data || 'Unknown error'}`);
          }
        } catch (error) {
          console.error("Error getting script:", error);
          alert("Failed to get embed script. Check console for details.");
        }
        break;
      case "delete":
        if (confirm(`Are you sure you want to archive "${tourName}"? The tour will be moved to the archive folder and permanently deleted after 90 days.`)) {
          try {
            const formData = new FormData();
            formData.append("action", "h3tm_delete_tour");
            formData.append("tour_name", tourName);
            formData.append("nonce", window.h3tm_ajax?.nonce || "");

            const response = await fetch(window.h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php", {
              method: "POST",
              body: formData,
            });

            const data = await response.json();
            if (data.success) {
              await loadTours();
            } else {
              alert(`Failed to delete: ${data.data || 'Unknown error'}`);
            }
          } catch (error) {
            console.error("Error deleting tour:", error);
            alert("Failed to delete tour. Check console for details.");
          }
        }
        break;
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-blue-500" />
      </div>
    );
  }

  if (tours.length === 0) {
    return (
      <div className="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed">
        <div className="flex flex-col items-center gap-2">
          <div className="rounded-full bg-gray-100 p-3">
            <Eye className="h-6 w-6 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900">No tours available</h3>
          <p className="text-sm text-gray-500">Upload your first tour to get started</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <p className="text-sm text-gray-600">
          {tours.length} {tours.length === 1 ? "tour" : "tours"} found
        </p>
        <Button
          onClick={() => {
            loadTours();
            onRefresh?.();
          }}
          variant="outline"
          size="sm"
          className="gap-2"
        >
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      </div>

      <div className="rounded-lg border border-gray-200 overflow-hidden bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 bg-gray-50">
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Tour Name
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  URL
                </th>
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {tours.map((tour) => (
                <tr key={tour.name} className="hover:bg-gray-50 transition-colors">
                  <td className="px-6 py-4 whitespace-nowrap">
                    {editingTour === tour.name ? (
                      <div className="flex items-center gap-2">
                        <Input
                          value={editValue}
                          onChange={(e) => setEditValue(e.target.value)}
                          onKeyDown={(e) => {
                            if (e.key === "Enter") saveEdit(tour.name);
                            if (e.key === "Escape") cancelEdit();
                          }}
                          className="h-8 text-sm"
                          autoFocus
                        />
                        <div className="flex gap-1">
                          <button
                            onClick={() => saveEdit(tour.name)}
                            className="p-1 hover:bg-green-50 rounded text-green-600"
                            title="Save"
                          >
                            <CheckCircle2 className="h-4 w-4" />
                          </button>
                          <button
                            onClick={cancelEdit}
                            className="p-1 hover:bg-red-50 rounded text-red-600"
                            title="Cancel"
                          >
                            <XCircle className="h-4 w-4" />
                          </button>
                        </div>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-gray-900">
                          {tour.name}
                        </span>
                        {tour.status && tour.status !== "completed" && (
                          <span className={`px-2 py-1 text-xs rounded-full ${
                            tour.status === "processing"
                              ? "bg-yellow-100 text-yellow-800"
                              : tour.status === "uploading"
                              ? "bg-blue-100 text-blue-800"
                              : "bg-red-100 text-red-800"
                          }`}>
                            {tour.status}
                          </span>
                        )}
                      </div>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <a
                      href={tour.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-sm text-blue-600 hover:text-blue-800 hover:underline truncate block max-w-md"
                    >
                      {tour.url}
                    </a>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center justify-end gap-1">
                      <button
                        onClick={() => handleAction("view", tour.name, tour.url)}
                        className="p-2 hover:bg-blue-50 rounded-md transition-colors group"
                        title="View Tour"
                      >
                        <Eye className="h-4 w-4 text-gray-500 group-hover:text-blue-600" />
                      </button>
                      <button
                        onClick={() => handleAction("changeUrl", tour.name, tour.url)}
                        className="p-2 hover:bg-purple-50 rounded-md transition-colors group"
                        title="Change URL"
                      >
                        <Link2 className="h-4 w-4 text-gray-500 group-hover:text-purple-600" />
                      </button>
                      <button
                        onClick={() => handleAction("update", tour.name)}
                        className="p-2 hover:bg-green-50 rounded-md transition-colors group"
                        title="Update Tour"
                      >
                        <RefreshCw className="h-4 w-4 text-gray-500 group-hover:text-green-600" />
                      </button>
                      <button
                        onClick={() => handleAction("rename", tour.name)}
                        className="p-2 hover:bg-amber-50 rounded-md transition-colors group"
                        title="Rename Tour"
                      >
                        <Pencil className="h-4 w-4 text-gray-500 group-hover:text-amber-600" />
                      </button>
                      <button
                        onClick={() => handleAction("getScript", tour.name)}
                        className="p-2 hover:bg-indigo-50 rounded-md transition-colors group"
                        title="Get Script"
                      >
                        <Code className="h-4 w-4 text-gray-500 group-hover:text-indigo-600" />
                      </button>
                      <button
                        onClick={() => handleAction("delete", tour.name)}
                        className="p-2 hover:bg-red-50 rounded-md transition-colors group"
                        title="Delete Tour"
                      >
                        <Trash2 className="h-4 w-4 text-gray-500 group-hover:text-red-600" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
