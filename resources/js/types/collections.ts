export interface LibraryCollection {
  id: number
  name: string
  books_count?: number
}

export interface CollectionPage {
  data: LibraryCollection[]
  meta: {
    current_page: number
    last_page: number
    total: number
    total_books_count: number
    uncollected_books_count: number
  }
}
