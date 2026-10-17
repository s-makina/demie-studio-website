/**
 * Portal API client (Task 8). The API uses session cookies, so every call
 * goes out with `credentials: "include"` and a 401 means "not signed in".
 * Token-URL pages (`/p/<token>`) need no session at all.
 */

export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "";

export class ApiError extends Error {
  readonly status: number;

  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

/** Aborted fetches (unmount / StrictMode remount) are not real errors. */
export function isAbort(error: unknown): boolean {
  return (
    (typeof DOMException !== "undefined" &&
      error instanceof DOMException &&
      error.name === "AbortError") ||
    (error instanceof Error && error.name === "AbortError")
  );
}

export interface Project {
  id: number;
  name: string;
  clientName: string | null;
  status: string;
}

export interface TokenProject {
  id: number;
  name: string;
  status: string;
}

export interface MediaView {
  id: number;
  kind: "photo" | "video" | string;
  sort: number;
  status: string;
  derivativeStatus: string;
  /**
   * Thumb pixel size, used to lay out the masonry before anything decodes.
   * `null` = not recorded yet (row is still being processed), `0` = thumb
   * exists but its size could not be read. Both mean "use the fallback aspect".
   */
  thumbWidth: number | null;
  thumbHeight: number | null;
  /** Upload instant, ISO-8601; drives the upload-day timeline. */
  createdAt: string;
  thumbUrl: string | null;
  previewUrl: string | null;
}

export interface MediaPage {
  items: MediaView[];
  /** Next keyset cursor; empty string when the page ran out. */
  nextCursor: string;
  /**
   * Cursor past this page's last row even on the final page, so a client can
   * resume loading forward after new uploads land.
   */
  endCursor: string;
  /** Total media in the project, for the timeline scrubber. */
  total: number;
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    credentials: "include",
    headers: { "Content-Type": "application/json", ...(init?.headers ?? {}) },
  });

  if (response.status === 204) {
    return undefined as T;
  }

  if (!response.ok) {
    if (response.status === 401) {
      throw new ApiError(401, "Not signed in.");
    }
    if (response.status === 403) {
      throw new ApiError(403, "You don't have access to this.");
    }
    if (response.status === 404) {
      throw new ApiError(404, "Not found — the link may be revoked.");
    }
    throw new ApiError(response.status, "Request failed. Please try again.");
  }

  return (await response.json()) as T;
}

/** Current session, or throws 401. Role drives the admin console. */
export interface Me {
  id: number;
  email: string;
  name: string | null;
  role: "admin" | "client" | string;
  status: string;
}

export function fetchMe(signal?: AbortSignal): Promise<Me> {
  return request("/auth/me", { signal });
}

export function logout(): Promise<void> {
  return request("/auth/logout", { method: "POST" });
}

/** Projects visible to the signed-in account. */
export function fetchProjects(signal?: AbortSignal): Promise<Project[]> {
  return request("/projects", { signal });
}

export function fetchProject(id: number, signal?: AbortSignal): Promise<Project> {
  return request(`/projects/${id}`, { signal });
}

/** Photographer creates a project (name + client name). */
export function createProject(name: string, clientName?: string): Promise<Project> {
  return request("/projects", {
    method: "POST",
    body: JSON.stringify({ name, clientName: clientName || null }),
  });
}

/** Photographer renames and/or archives a project. */
export function updateProject(
  id: number,
  patch: { name?: string; status?: "active" | "archived" }
): Promise<Project> {
  return request(`/projects/${id}`, {
    method: "PATCH",
    body: JSON.stringify(patch),
  });
}

export interface InviteResult {
  accountId: number;
  email: string;
  inviteToken: string | null;
  expiresAt: string | null;
  tokenIssued: boolean;
}

/** Photographer invites a client by email (one-time token, never stored). */
export function inviteClient(email: string, name?: string): Promise<InviteResult> {
  return request("/auth/invite", {
    method: "POST",
    body: JSON.stringify({ email, name: name || null }),
  });
}

/** Photographer assigns an invited email to a project (idempotent). */
export function assignMember(
  projectId: number,
  email: string
): Promise<{ projectId: number; email: string }> {
  return request(`/projects/${projectId}/members`, {
    method: "POST",
    body: JSON.stringify({ email }),
  });
}

/** Full client-facing invite-accept URL for a raw token (same origin). */
export function acceptUrl(token: string): string {
  if (typeof window !== "undefined") {
    return `${window.location.origin}/login/accept?token=${encodeURIComponent(token)}`;
  }
  return `/login/accept?token=${encodeURIComponent(token)}`;
}

/** Project behind a share-token URL (no login). */
export function fetchTokenProject(token: string, signal?: AbortSignal): Promise<TokenProject> {
  return request(`/p/${token}`, { signal });
}

export interface MediaAccess {
  /** Listing endpoint path, e.g. `/projects/7/media` or `/p/abc/media`. */
  listPath: string;
  /** Prefix for the per-file download endpoint. */
  downloadPrefix: string;
  /** ZIP request/poll endpoint, e.g. `/projects/7/zip` or `/p/abc/zip`. */
  zipPath: string;
}

export function accountAccess(projectId: number): MediaAccess {
  return {
    listPath: `/projects/${projectId}/media`,
    downloadPrefix: "/media",
    zipPath: `/projects/${projectId}/zip`,
  };
}

export function tokenAccess(token: string): MediaAccess {
  return {
    listPath: `/p/${token}/media`,
    downloadPrefix: `/p/${token}/media`,
    zipPath: `/p/${token}/zip`,
  };
}

/** Cursor page of media; pass the previous `nextCursor` (or omit to start). */
export function fetchMediaPage(
  access: MediaAccess,
  options: {
    cursor?: string;
    /** Seek straight to this position in display order. */
    offset?: number;
    limit?: number;
    signal?: AbortSignal;
  } = {}
): Promise<MediaPage> {
  const params = new URLSearchParams({ limit: String(options.limit ?? 60) });
  if (options.cursor) {
    params.set("cursor", options.cursor);
  }
  if (options.offset !== undefined && options.offset > 0) {
    params.set("offset", String(Math.floor(options.offset)));
  }
  return request(`${access.listPath}?${params}`, { signal: options.signal });
}

/**
 * Fresh single-item view. Listing URLs are presigned and expire (15 min), so a
 * tile whose image fails to load refreshes itself through this instead of
 * showing a broken thumb.
 */
export function fetchMediaItem(
  access: MediaAccess,
  mediaId: number,
  signal?: AbortSignal
): Promise<MediaView> {
  return request(`${access.downloadPrefix}/${mediaId}`, { signal });
}

/** Direct API URL for a per-file download (browser follows the 302 to S3). */
export function downloadUrl(access: MediaAccess, mediaId: number): string {
  return `${API_URL}${access.downloadPrefix}/${mediaId}/download`;
}

export interface ZipJob {
  id: number;
  projectId: number;
  status: "pending" | "running" | "ready" | "expired" | "failed" | string;
  mediaCount: number;
  sizeBytes: number | null;
  error: string | null;
  expiresAt: string | null;
}

export interface ZipRequest {
  job_id: number;
  status: string;
}

/**
 * ZIP request (Task 13). Omitted/empty `mediaIds` means all uploaded media;
 * otherwise exactly those files. Idempotent per selection — a live job for
 * the same files is resumed, never duplicated.
 */
export function requestZip(access: MediaAccess, mediaIds?: number[]): Promise<ZipRequest> {
  return request(`${access.zipPath}`, {
    method: "POST",
    body: JSON.stringify(mediaIds && mediaIds.length > 0 ? { media_ids: mediaIds } : {}),
  });
}

/** Poll one ZIP job (close the tab, come back, keep polling the same id). */
export function fetchZipJob(
  access: MediaAccess,
  jobId: number,
  signal?: AbortSignal
): Promise<ZipJob> {
  return request(`${access.zipPath}/${jobId}`, { signal });
}

/** Direct API URL for a finished ZIP (browser follows the 302 to S3). */
export function zipDownloadUrl(access: MediaAccess, jobId: number): string {
  return `${API_URL}${access.zipPath}/${jobId}/download`;
}

export interface ShareStatus {
  projectId: number;
  issued: boolean;
  revoked: boolean;
}

export interface ProjectStats {
  projectId: number;
  views: number;
  downloads: number;
}

export interface IssuedShare {
  projectId: number;
  token: string;
}

/** Photographer-only share-link state (never includes the raw token). */
export function fetchShareStatus(projectId: number, signal?: AbortSignal): Promise<ShareStatus> {
  return request(`/projects/${projectId}/share/status`, { signal });
}

/** Photographer-only per-project view/download totals. */
export function fetchProjectStats(projectId: number, signal?: AbortSignal): Promise<ProjectStats> {
  return request(`/projects/${projectId}/stats`, { signal });
}

/** Create the project's share link (returned once). 409 = already issued, rotate instead. */
export function issueShare(projectId: number): Promise<IssuedShare> {
  return request(`/projects/${projectId}/share`, { method: "POST" });
}

/** Rotate the share link: old stops working instantly, new is returned once. */
export function rotateShare(projectId: number): Promise<IssuedShare> {
  return request(`/projects/${projectId}/share/rotate`, { method: "POST" });
}

/** Revoke the share link: denied instantly. Idempotent. */
export function revokeShare(projectId: number): Promise<void> {
  return request(`/projects/${projectId}/share/revoke`, { method: "POST" });
}

/** Full client-facing share URL for a raw token (same web origin, `/p/<token>`). */
export function shareUrl(token: string): string {
  if (typeof window !== "undefined") {
    return `${window.location.origin}/p/${token}`;
  }
  return `/p/${token}`;
}

// ------------------------------------------------------------ upload (Task 20)

export interface UploadInit {
  mediaId: number;
  key: string;
  uploadId: string;
}

export interface UploadPart {
  url: string;
  expiresAt: number;
}

export interface CompletedUpload {
  id: number;
  s3Key: string;
  sizeBytes: number;
  kind: string;
  sort: number;
  status: string;
}

/** Photographer reserves a row + opens the S3 multipart upload. */
export function initUpload(
  projectId: number,
  file: { filename: string; contentType: string; sizeBytes: number }
): Promise<UploadInit> {
  return request(`/projects/${projectId}/media/init`, {
    method: "POST",
    body: JSON.stringify({
      filename: file.filename,
      contentType: file.contentType,
      sizeBytes: file.sizeBytes,
    }),
  });
}

/** Photographer gets a presigned URL for one part. */
export function uploadPartUrl(mediaId: number, partNumber: number): Promise<UploadPart> {
  return request(`/media/${mediaId}/parts`, {
    method: "POST",
    body: JSON.stringify({ partNumber }),
  });
}

/** Photographer closes the upload with browser-collected part ETags. */
export function completeUpload(
  mediaId: number,
  parts: Array<{ partNumber: number; etag: string }>
): Promise<CompletedUpload> {
  return request(`/media/${mediaId}/complete`, {
    method: "POST",
    body: JSON.stringify({ parts }),
  });
}

/** Photographer abandons an open upload (aborts MPU, drops the row). */
export function abortUpload(mediaId: number): Promise<void> {
  return request(`/media/${mediaId}/abort`, { method: "POST" });
}

/** Photographer removes a finished photo (Task 21). Blocked while uploading. */
export function deleteMedia(mediaId: number): Promise<void> {
  return request(`/media/${mediaId}`, { method: "DELETE" });
}

/** Kind guess matching the API (image/* photo, video/* video). */
export function kindForFile(file: File): "photo" | "video" | null {
  if (file.type.startsWith("image/")) {
    return "photo";
  }
  if (file.type.startsWith("video/")) {
    return "video";
  }
  const lower = file.name.toLowerCase();
  if (/\.(jpg|jpeg|png|webp|gif|heic|heif|avif|tif|tiff|bmp)$/.test(lower)) {
    return "photo";
  }
  if (/\.(mp4|mov|m4v|webm|mkv)$/.test(lower)) {
    return "video";
  }
  return null;
}
