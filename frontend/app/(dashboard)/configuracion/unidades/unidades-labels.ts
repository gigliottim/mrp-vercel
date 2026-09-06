export const TIPOS = ['longitud', 'superficie', 'volumen', 'masa', 'tiempo', 'temperatura', 'unidad'] as const

export const TIPO_LABELS: Record<string, string> = {
  longitud: 'Longitud',
  superficie: 'Superficie',
  volumen: 'Volumen',
  masa: 'Masa',
  tiempo: 'Tiempo',
  temperatura: 'Temperatura',
  unidad: 'Unidad',
}

export function tipoLabel(tipo: string): string {
  return TIPO_LABELS[tipo] ?? tipo
}
