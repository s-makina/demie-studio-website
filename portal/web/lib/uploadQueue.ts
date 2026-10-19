/**
 * Persisted upload queue (IndexedDB).
 *
 * Each queued file is stored under `<projectId>:<clientKey>` (the same
 * idempotency key the API dedupes on) so a page refresh mid-batch can
 * restore the queue and resume: init converges to the already-open upload,
 * already-finalized files report `alreadyUploaded`, and only genuinely
 * unfinished files re-send bytes. `File` objects are structured-cloneable,
 * so the bytes survive without re-picking the folder.
 *
 * Every operation degrades to a no-op when IndexedDB is unavailable
 * (private modes, storage pressure): the in-memory queue then behaves
 * exactly as before — uploads still work, there is simply nothing to
 * resume after a refresh.
 */

const DB_NAME = "demie-upload-queue";
const STORE = "files";

export interface QueuedFile {
  projectId: number;
  clientKey: string;
  file: File;
}

let dbPromise: Promise<IDBDatabase> | null = null;

function openDb(): Promise<IDBDatabase> {
  if (typeof indexedDB === "undefined") {
    return Promise.reject(new Error("IndexedDB unavailable"));
  }
  if (!dbPromise) {
    dbPromise = new Promise((resolve, reject) => {
      const open = indexedDB.open(DB_NAME, 1);
      open.onupgradeneeded = () => {
        if (!open.result.objectStoreNames.contains(STORE)) {
          open.result.createObjectStore(STORE, { keyPath: "id" });
        }
      };
      open.onblocked = () =>
        reject(new Error("IndexedDB blocked by another connection"));
      open.onsuccess = () => resolve(open.result);
      open.onerror = () => {
        dbPromise = null; // allow a later retry
        reject(open.error ?? new Error("IndexedDB open failed"));
      };
    });
  }
  return dbPromise;
}

function rowKey(projectId: number, clientKey: string): string {
  return `${projectId}:${clientKey}`;
}

/** Run one readwrite micro-transaction; any failure is swallowed. */
async function write(
  action: (store: IDBObjectStore) => IDBRequest | undefined
): Promise<void> {
  try {
    const db = await openDb();
    await new Promise<void>((resolve) => {
      try {
        const tx = db.transaction(STORE, "readwrite");
        const request = action(tx.objectStore(STORE));
        if (request) {
          request.onerror = () => undefined; // swallow row errors
        }
        tx.oncomplete = () => resolve();
        tx.onabort = () => resolve();
        tx.onerror = () => resolve();
      } catch {
        resolve();
      }
    });
  } catch {
    // Degraded mode: no persistence, uploads continue as before.
  }
}

/** Queue one file; replaces any earlier record with the same clientKey. */
export function putQueuedFile({ projectId, clientKey, file }: QueuedFile): Promise<void> {
  return write((store) =>
    store.put({
      id: rowKey(projectId, clientKey),
      projectId,
      clientKey,
      file,
    })
  );
}

/** Drop one queued record (user removed the tile / batch finished it). */
export function deleteQueuedFile(projectId: number, clientKey: string): Promise<void> {
  return write((store) => store.delete(rowKey(projectId, clientKey)));
}

/**
 * Restore the queue for one project. Everything hydrates as `queued`
 * regardless of its pre-refresh state — the API's idempotent init settles
 * finished files in one cheap round trip.
 */
export async function listQueuedFiles(
  projectId: number
): Promise<Array<{ clientKey: string; file: File }>> {
  try {
    const db = await openDb();
    return await new Promise((resolve) => {
      try {
        const request = db.transaction(STORE, "readonly")
          .objectStore(STORE)
          .getAll();
        request.onerror = () => resolve([]);
        request.onsuccess = () => {
          const rows = (request.result ?? []) as Array<{
            projectId: number;
            clientKey: string;
            file: File;
          }>;
          resolve(
            rows
              .filter((row) => row.projectId === projectId)
              .map(({ clientKey, file }) => ({ clientKey, file }))
          );
        };
      } catch {
        resolve([]);
      }
    });
  } catch {
    return [];
  }
}
