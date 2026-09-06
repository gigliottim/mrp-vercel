import ExcelJS from 'exceljs'

export type SheetSpec = { name: string; rows: (string | number | null)[][] }

export async function buildXlsx(sheets: SheetSpec[]): Promise<Buffer> {
  const wb = new ExcelJS.Workbook()
  for (const sheet of sheets) {
    const ws = wb.addWorksheet(sheet.name.slice(0, 31))
    if (sheet.rows.length === 0) continue
    const header = sheet.rows[0]
    ws.addRow(header.map((h) => h ?? ''))
    ws.getRow(1).font = { bold: true }
    for (let i = 1; i < sheet.rows.length; i++) {
      ws.addRow(sheet.rows[i].map((v) => v ?? ''))
    }
    ws.columns.forEach((col, idx) => {
      const maxLen = Math.max(...sheet.rows.map((r) => String(r[idx] ?? '').length), 10)
      col.width = Math.min(maxLen + 2, 60)
    })
  }
  const out = await wb.xlsx.writeBuffer()
  return Buffer.from(out)
}
