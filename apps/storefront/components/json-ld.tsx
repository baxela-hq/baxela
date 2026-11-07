// Renders one JSON-LD script per graph (gap-B2, D8). Serialization is
// safety-critical: `<` and line-separator characters are escaped so page
// content can never break out of the script element.

interface JsonLdProps {
  data: object | object[];
}

function serialize(value: object | object[]): string {
  return JSON.stringify(value)
    .replace(/</g, "\\u003c")
    .replace(/\u2028/g, "\\u2028")
    .replace(/\u2029/g, "\\u2029");
}

export function JsonLd({ data }: JsonLdProps) {
  return (
    <script
      type='application/ld+json'
      // The payload is JSON.stringify output with HTML-significant
      // characters escaped — no untrusted markup can surface here.
      dangerouslySetInnerHTML={{ __html: serialize(data) }}
    />
  );
}
