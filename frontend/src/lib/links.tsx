import type { ReactNode } from 'react'

// El mismo patrón que LinkPreviewer::URL_PATTERN en el backend
const URL_PATTERN = /\b(?:https?:\/\/|www\.)[^\s<>"']+/giu
const TRAILING = /[.,;:!?)\]}]+$/

/** Primer enlace del texto (sin la puntuación final), o null. */
export function firstUrl(text: string): string | null {
  const match = text.match(new RegExp(URL_PATTERN.source, 'iu'))
  return match ? match[0].replace(TRAILING, '') : null
}

function href(url: string): string {
  return /^www\./i.test(url) ? `https://${url}` : url
}

/** Texto con los enlaces convertidos en <a> que abren en otra pestaña. */
export function linkify(text: string): ReactNode[] {
  const nodes: ReactNode[] = []
  let last = 0
  for (const match of text.matchAll(URL_PATTERN)) {
    const url = match[0].replace(TRAILING, '')
    const start = match.index
    if (start > last) nodes.push(text.slice(last, start))
    nodes.push(
      <a key={start} href={href(url)} target="_blank" rel="noopener noreferrer nofollow ugc">
        {url}
      </a>,
    )
    last = start + url.length
  }
  if (last < text.length) nodes.push(text.slice(last))
  return nodes
}
