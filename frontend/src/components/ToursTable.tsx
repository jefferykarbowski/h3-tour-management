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
import { EmbedScriptModal } from "./EmbedScriptModal";
import { UpdateTourModal } from "./UpdateTourModal";

interface Tour {
  name: string;
  url: string;
  status?: "completed" | "processing" | "uploading" | "failed";
  tour_id?: string;
  updated_date?: string;
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
  const [embedModalOpen, setEmbedModalOpen] = useState(false);
  const [embedData, setEmbedData] = useState({
    tourName: "",
    tourUrl: "",
    embedScript: "",
    embedScriptResponsive: "",
  });
  const [updateModalOpen, setUpdateModalOpen] = useState(false);
  const [updateTourId, setUpdateTourId] = useState("");
  const [updateTourName, setUpdateTourName] = useState("");

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
        setTours(toursList.map((tour: any) => {
          // For new ID-based tours, use tour_slug for friendly URLs; for legacy tours, use name
          const tourIdentifier = (typeof tour === "object" && tour.tour_slug)
            ? tour.tour_slug
            : (typeof tour === "string" ? tour : tour.name);

          return {
            name: typeof tour === "string" ? tour : tour.name,
            url: window.location.origin + "/h3panos/" + encodeURIComponent(tourIdentifier),
            status: typeof tour === "string" ? "completed" : tour.status,
            tour_id: typeof tour === "string" ? undefined : tour.tour_id,
            updated_date: typeof tour === "string" ? undefined : tour.updated_date,
          };
        }));
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

  const handleAction = async (action: string, tourName: string, tourUrl?: string, tourId?: string) => {
    switch (action) {
      case "view":
        window.open(tourUrl, "_blank");
        break;
      case "changeUrl":
        const newUrl = prompt("Enter new URL slug (e.g., my-custom-tour-name):", tourUrl?.split('/').pop());
        if (newUrl && newUrl.trim()) {
          try {
            // Extract slug from input (handle both full URLs and plain slugs)
            let slug = newUrl.trim();
            if (slug.includes('/')) {
              // Extract last part of URL path
              slug = slug.split('/').filter(p => p).pop() || '';
            }

            // Validate slug format
            if (!slug || slug.length === 0) {
              alert("Invalid URL slug. Please enter a valid slug.");
              return;
            }

            const formData = new FormData();
            formData.append("action", "h3tm_change_tour_url");
            formData.append("tour_name", tourName);
            formData.append("new_slug", slug);  // Changed from new_url to new_slug
            formData.append("nonce", window.h3tm_ajax?.nonce || "");

            const response = await fetch(window.h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php", {
              method: "POST",
              body: formData,
            });

            const data = await response.json();
            if (data.success) {
              alert(`URL changed successfully for "${tourName}"\nNew URL: /h3panos/${slug}`);
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
        if (!tourId) {
          alert('Cannot update legacy tour without tour_id. Please re-upload as a new tour.');
          return;
        }
        // Open update modal with tour info
        setUpdateTourId(tourId);
        setUpdateTourName(tourName);
        setUpdateModalOpen(true);
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
            // Backend returns: { embed_script, embed_script_responsive, tour_url, tour_name }
            setEmbedData({
              tourName: data.data.tour_name,
              tourUrl: data.data.tour_url,
              embedScript: data.data.embed_script,
              embedScriptResponsive: data.data.embed_script_responsive,
            });
            setEmbedModalOpen(true);
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
            console.log("🗑️ Delete Request Starting:", {
              tourName,
              ajaxUrl: window.h3tm_ajax?.ajax_url,
              hasNonce: !!window.h3tm_ajax?.nonce,
              timestamp: new Date().toISOString()
            });

            const formData = new FormData();
            formData.append("action", "h3tm_delete_tour");
            formData.append("tour_name", tourName);
            formData.append("nonce", window.h3tm_ajax?.nonce || "");

            console.log("📤 Sending DELETE request to:", window.h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php");

            const response = await fetch(window.h3tm_ajax?.ajax_url || "/wp-admin/admin-ajax.php", {
              method: "POST",
              body: formData,
            });

            console.log("📥 Response received:", {
              status: response.status,
              statusText: response.statusText,
              ok: response.ok,
              headers: {
                contentType: response.headers.get("content-type")
              }
            });

            const responseText = await response.text();
            console.log("📄 Raw response body:", responseText);

            let data;
            try {
              data = JSON.parse(responseText);
            } catch (parseError) {
              console.error("❌ JSON parse error:", parseError);
              console.error("Raw response that failed to parse:", responseText);
              alert(`Server returned invalid JSON response. Check console for details.\n\nResponse preview: ${responseText.substring(0, 200)}`);
              return;
            }

            console.log("✅ Parsed response:", data);

            if (data.success) {
              console.log("✓ Delete successful, reloading tours...");
              await loadTours();
              alert(`Tour "${tourName}" archived successfully! ${data.data || ''}`);
            } else {
              console.error("❌ Delete failed:", data.data);
              alert(`Failed to delete: ${data.data || 'Unknown error'}\n\nCheck WordPress error logs for "H3TM S3 Archive:" messages.`);
            }
          } catch (error) {
            console.error("💥 Delete operation exception:", {
              error,
              errorMessage: error instanceof Error ? error.message : String(error),
              errorStack: error instanceof Error ? error.stack : undefined,
              tourName
            });
            alert(`Failed to delete tour "${tourName}".\n\nError: ${error instanceof Error ? error.message : String(error)}\n\nCheck browser console and WordPress error logs for more details.`);
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
    <>
      <EmbedScriptModal
        isOpen={embedModalOpen}
        onClose={() => setEmbedModalOpen(false)}
        tourName={embedData.tourName}
        tourUrl={embedData.tourUrl}
        embedScript={embedData.embedScript}
        embedScriptResponsive={embedData.embedScriptResponsive}
      />

      <UpdateTourModal
        isOpen={updateModalOpen}
        onClose={() => setUpdateModalOpen(false)}
        tourId={updateTourId}
        tourName={updateTourName}
        onUpdateComplete={() => {
          loadTours();
          onRefresh?.();
        }}
      />

      <div className="space-y-4">
        <div className="flex justify-between items-center">
        <p className="text-sm text-gray-600">
          {tours.length} {tours.length === 1 ? "tour" : "tours"} found
        </p>
        <div className="flex gap-2">
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
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Last Updated
                </th>
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {tours.map((tour) => (
                <tr key={tour.tour_id || tour.name} className="hover:bg-gray-50 transition-colors">
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
                      <div className="flex flex-col gap-1">
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
                        {tour.tour_id && (
                          <span className="text-xs font-mono text-gray-500" title="Tour ID (immutable identifier)">
                            ID: {tour.tour_id}
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
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className="text-sm text-gray-500">
                      {tour.updated_date
                        ? new Date(tour.updated_date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                          })
                        : '-'}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center justify-end gap-1">
                      <button
                        onClick={() => handleAction("view", tour.name, tour.url, tour.tour_id)}
                        className="p-2 hover:bg-blue-50 rounded-md transition-colors group"
                        title="View Tour"
                      >
                        <Eye className="h-4 w-4 text-gray-500 group-hover:text-blue-600" />
                      </button>
                      <button
                        onClick={() => handleAction("changeUrl", tour.name, tour.url, tour.tour_id)}
                        className="p-2 hover:bg-purple-50 rounded-md transition-colors group"
                        title="Change URL"
                      >
                        <Link2 className="h-4 w-4 text-gray-500 group-hover:text-purple-600" />
                      </button>
                      <button
                        onClick={() => handleAction("rename", tour.name, tour.url, tour.tour_id)}
                        className="p-2 hover:bg-amber-50 rounded-md transition-colors group"
                        title="Rename Tour"
                      >
                        <Pencil className="h-4 w-4 text-gray-500 group-hover:text-amber-600" />
                      </button>
                      <button
                        onClick={() => handleAction("update", tour.name, tour.url, tour.tour_id)}
                        className="p-2 hover:bg-green-50 rounded-md transition-colors group"
                        title="Update Tour"
                        disabled={!tour.tour_id}
                      >
                        <RefreshCw className={`h-4 w-4 ${!tour.tour_id ? 'text-gray-300' : 'text-gray-500 group-hover:text-green-600'}`} />
                      </button>
                      <button
                        onClick={() => handleAction("getScript", tour.name, tour.url, tour.tour_id)}
                        className="p-2 hover:bg-indigo-50 rounded-md transition-colors group"
                        title="Get Script"
                      >
                        <Code className="h-4 w-4 text-gray-500 group-hover:text-indigo-600" />
                      </button>
                      <button
                        onClick={() => handleAction("delete", tour.name, tour.url, tour.tour_id)}
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
    </>
  );
}
