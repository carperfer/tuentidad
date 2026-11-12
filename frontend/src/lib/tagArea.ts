import type { Photo, TagArea } from '../api/photos'

/** Lado de la zona que se crea con un clic, en % del ancho de la foto. */
const CLICK_SIZE = 20

export const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value))
export const round = (value: number) => Math.round(value * 100) / 100

/** Zona cuadrada centrada en un punto, dentro de la foto. */
export function areaAround(photo: Photo, x: number, y: number): TagArea {
  const width = CLICK_SIZE
  const height = Math.min(100, (CLICK_SIZE * photo.width) / photo.height)
  return {
    x: round(clamp(x - width / 2, 0, 100 - width)),
    y: round(clamp(y - height / 2, 0, 100 - height)),
    width,
    height: round(height),
  }
}
