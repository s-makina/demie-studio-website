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
  thumbUrl: string | null;
  previewUrl: string | null;
}

export interface MediaPage {
  items: MediaView[];
  nextCursor: string;
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

/** Current session, or throws 401. */
export function fetchMe(signal?: AbortSignal): Promise<{ email: string }> {
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

/** Project behind a share-token URL (no login). */
export function fetchTokenProject(token: string, signal?: AbortSignal): Promise<TokenProject> {
  return request(`/p/${token}`, { signal });
}

export interface MediaAccess {
  /** Listing endpoint path, e.g. `/projects/7/media` or `/p/abc/media`. */
  listPath: string;
  /** Prefix for the per-file download endpoint. */
  downloadPrefix: string;
}

export function accountAccess(projectId: number): MediaAccess {
  return {
    listPath: `/projects/${projectId}/media`,
    downloadPrefix: "/media",
  };
}

export function tokenAccess(token: string): MediaAccess {
  return {
    listPath: `/p/${token}/media`,
    downloadPrefix: `/p/${token}/media`,
  };
}

/** Cursor page of media; pass the previous `nextCursor` (or omit to start). */
export function fetchMediaPage(
  access: MediaAccess,
  cursor?: string,
  limit = 60,
  signal?: AbortSignal
): Promise<MediaPage> {
  const params = new URLSearchParams({ limit: String(limit) });
  if (cursor) {
    params.set("cursor", cursor);
  }
  return request(`${access.listPath}?${params}`, { signal });
}

/** Direct API URL for a per-file download (browser follows the 302 to S3). */
export function downloadUrl(access: MediaAccess, mediaId: number): string {
  return `${API_URL}${access.downloadPrefix}/${mediaId}/download`;
}
