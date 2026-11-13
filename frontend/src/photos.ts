const MAX_SIDE = 2048
const QUALITY = 0.85

const looksHeic = (file: File) => /image\/hei[cf]/i.test(file.type) || /\.hei[cf]$/i.test(file.name)

async function decodeHeic(file: File): Promise<ImageBitmap> {
  const { heicTo } = await import('heic-to')
  return heicTo({ blob: file, type: 'bitmap' })
}

async function decode(file: File): Promise<ImageBitmap> {
  try {
    return await createImageBitmap(file, { imageOrientation: 'from-image' })
  } catch (error) {
    if (looksHeic(file)) return decodeHeic(file)
    throw error
  }
}

export class PhotoError extends Error {}

export async function preparePhoto(file: File): Promise<File> {
  let bitmap: ImageBitmap
  try {
    bitmap = await decode(file)
  } catch {
    throw new PhotoError('Não foi possível abrir esta imagem. Envie uma foto em JPG, PNG ou HEIC.')
  }

  const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height))
  const width = Math.round(bitmap.width * scale)
  const height = Math.round(bitmap.height * scale)

  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const context = canvas.getContext('2d')!
  context.fillStyle = '#fff'
  context.fillRect(0, 0, width, height)
  context.drawImage(bitmap, 0, 0, width, height)
  bitmap.close()

  const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY))
  if (!blob) throw new PhotoError('Não foi possível preparar a foto para envio.')

  const name = file.name.replace(/\.[^.]+$/, '') || 'foto'
  return new File([blob], `${name}.jpg`, { type: 'image/jpeg' })
}
