export type AvatarSize = 24 | 32 | 40 | 50 | 75 | 96 | 200

type AvatarProps = {
  src: string | null | undefined
  /** Nombre de la persona; si la foto acompaña a su nombre visible, usa decorative. */
  name: string
  size: AvatarSize
  decorative?: boolean
}

/** Foto de una persona con marco; sin foto, muestra su inicial (guía §7.5). */
export function Avatar({ src, name, size, decorative = false }: AvatarProps) {
  const label = decorative ? undefined : `Foto de ${name}`

  if (src) {
    return <img className={`avatar avatar--${size}`} src={src} alt={label ?? ''} width={size} height={size} />
  }

  return (
    <span className={`avatar avatar--${size} avatar--empty`} role={label ? 'img' : undefined} aria-label={label} aria-hidden={label ? undefined : true}>
      {name.trim().charAt(0).toUpperCase()}
    </span>
  )
}
