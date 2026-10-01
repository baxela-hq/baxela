import DOMPurify from "isomorphic-dompurify";

/**
 * Sanitizes backend-authored rich text (admin CMS content, settings values)
 * before it is rendered as HTML. Strips scripts and event handlers while
 * preserving the tags/styles the Tiptap editor produces.
 */
export function sanitizeHtml(html: string): string {
  return DOMPurify.sanitize(html, {
    ADD_ATTR: ["target", "rel"],
    FORBID_TAGS: ["script", "iframe", "object", "embed", "form"],
  });
}
