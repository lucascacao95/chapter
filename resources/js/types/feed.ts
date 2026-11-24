export interface FeedSharing {
  id: number
  status: 'pending' | 'published' | 'failed' | 'withdrawn'
}

export interface SharedBook {
  title: string
  authors: string[] | null
  cover_url: string | null
}

export interface FeedActivity {
  id: number
  type: 'book_added' | 'book_rated'
  actor: { id: number; name: string }
  book: SharedBook
  rating: { value: number; review: string | null } | null
  occurred_at: string
  published_at: string
  can_withdraw: boolean
}

export interface FeedPage {
  data: FeedActivity[]
  links: { next: string | null; prev: string | null }
}

export interface RecentBook extends SharedBook {
  id: number
  recent_activity_at: string
}

export const sharingLabel = (activity: FeedSharing | null | undefined) => !activity ? 'Avaliação privada.'
  : activity.status === 'pending' ? 'Compartilhamento aguardando processamento ou publicação.'
    : activity.status === 'published' ? 'Compartilhado no feed.'
      : activity.status === 'withdrawn' ? 'Retirado do feed.' : 'Não foi possível publicar no feed. O registro continua salvo na biblioteca.'
