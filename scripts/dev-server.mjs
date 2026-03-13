import http from "node:http";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, "..");
const port = Number(process.env.PORT || 4173);

const mimeTypes = {
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".webp": "image/webp",
  ".gif": "image/gif",
  ".ico": "image/x-icon",
  ".txt": "text/plain; charset=utf-8",
};

function send(res, statusCode, body, headers = {}) {
  res.writeHead(statusCode, headers);
  res.end(body);
}

function resolveRequestPath(urlPath) {
  const normalized = decodeURIComponent((urlPath || "/").split("?")[0]);
  const requested = normalized === "/" ? "/index.html" : normalized;
  const filePath = path.normalize(path.join(rootDir, requested));

  if (!filePath.startsWith(rootDir)) {
    return null;
  }

  return filePath;
}

const server = http.createServer((req, res) => {
  const filePath = resolveRequestPath(req.url);

  if (!filePath) {
    send(res, 403, "Forbidden", { "Content-Type": "text/plain; charset=utf-8" });
    return;
  }

  fs.stat(filePath, (statError, stats) => {
    if (statError) {
      send(res, 404, "Not found", { "Content-Type": "text/plain; charset=utf-8" });
      return;
    }

    const finalPath = stats.isDirectory() ? path.join(filePath, "index.html") : filePath;

    fs.readFile(finalPath, (readError, file) => {
      if (readError) {
        send(res, 404, "Not found", { "Content-Type": "text/plain; charset=utf-8" });
        return;
      }

      const ext = path.extname(finalPath).toLowerCase();
      const contentType = mimeTypes[ext] || "application/octet-stream";
      send(res, 200, file, { "Content-Type": contentType });
    });
  });
});

server.listen(port, "127.0.0.1", () => {
  console.log(`Dev server running at http://127.0.0.1:${port}`);
});

server.on("error", (error) => {
  console.error(`Dev server failed: ${error.message}`);
  process.exit(1);
});
