import { PDFDocument, StandardFonts, rgb } from 'pdf-lib'

const PAGE_W = 595.28 // A4
const MARGIN = 40
const COL_PAD = 6

export type PdfTableOpts = {
  title: string
  subtitle?: string
  headers: string[]
  rows: (string | number | null)[][]
}

// Codifica a WinAnsi (Latin-1) para StandardFonts: reemplaza lo no soportado
function latin(s: unknown): string {
  return String(s ?? '').replace(/[^\x00-\xFF]/g, '?')
}

export async function buildPdf(opts: PdfTableOpts): Promise<Buffer> {
  const doc = await PDFDocument.create()
  const font = await doc.embedFont(StandardFonts.Helvetica)
  const fontBold = await doc.embedFont(StandardFonts.HelveticaBold)
  const colCount = opts.headers.length
  const colW = (PAGE_W - MARGIN * 2) / colCount

  let page = doc.addPage([PAGE_W, 841.89])
  let y = 841.89 - MARGIN

  const ensureSpace = (needed: number) => {
    if (y - needed < MARGIN) {
      page = doc.addPage([PAGE_W, 841.89])
      y = 841.89 - MARGIN
    }
  }

  const drawHeader = () => {
    page.drawText(latin(opts.title), { x: MARGIN, y, size: 14, font: fontBold })
    y -= 18
    if (opts.subtitle) {
      page.drawText(latin(opts.subtitle), { x: MARGIN, y, size: 9, font, color: rgb(0.4, 0.4, 0.4) })
      y -= 14
    }
    page.drawText(latin(opts.headers.join('  |  ')), { x: MARGIN, y, size: 8, font: fontBold })
    y -= 12
  }

  drawHeader()
  for (const row of opts.rows) {
    const cells = row.map((v, i) => {
      const maxChars = Math.floor((colW - COL_PAD * 2) / 4.6)
      return latin(v).slice(0, maxChars)
    })
    ensureSpace(14)
    // una celda por columna, truncada
    cells.forEach((cell, i) => {
      page.drawText(cell, { x: MARGIN + i * colW + COL_PAD, y, size: 8, font })
    })
    y -= 14
  }

  const bytes = await doc.save()
  return Buffer.from(bytes)
}
