import type { FeedSharing } from './feed'

export interface BookRating {
  feed_activity: FeedSharing | null
  id: number
  book_id: number
  book_file_id: number | null
  user_id: number
  rating: number
  review: string | null
  created_at: string
}

export interface RatingPage {
  data: BookRating[]
  meta: { current_page: number; last_page: number; total: number; per_page: number }
}

export interface RatingGroup {
  key: string
  label: string
  books_count: number
}

export const formatRating = (value: number) => value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })

export interface KindleDelivery {
  id: number
  status: 'queued' | 'processing' | 'submitted' | 'failed'
  error_code: string | null
  submitted_at: string | null
}

export interface Book {
  feed_activity: FeedSharing | null
  latest_rating: BookRating | null
  id: number
  title: string
  authors: string[] | null
  page_count: number | null
  publisher: string | null
  edition: string | null
  description: string | null
  language: string | null
  published_year: number | null
  isbn: string | null
  collection_id: number | null
  cover_url?: string | null
  kindle?: {
    configured: boolean
    max_attachment_bytes: number
    delivery: KindleDelivery | null
  }
  file?: {
    id: number
    original_name: string
    format: 'epub' | 'pdf'
    size_bytes: number
    processing_status: 'pending' | 'processing' | 'completed' | 'failed'
    processing_error_code: string | null
    processed_at: string | null
  } | null
  created_at: string
  updated_at: string
}

export interface BookPage {
  data: Book[]
  meta: { current_page: number; last_page: number; total: number; per_page: number }
}
