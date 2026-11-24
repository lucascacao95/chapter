export function focusBookActions(event: Event, bookId: number | null) {
  event.preventDefault()
  const trigger = [...document.querySelectorAll<HTMLButtonElement>(`[data-book-actions="${bookId}"]`)]
    .find(button => button.getClientRects().length > 0)
  const fallback = document.querySelector<HTMLButtonElement>('[aria-label="Visualização da biblioteca"] button[aria-pressed="true"]')
  const target = trigger ?? fallback
  target?.focus()
}
