export interface ProductVariant { id: number; color: string; size: string; stock: number }

export interface Product {
  id: number
  name: string
  description: string
  image: string | null
  price: string
  category: string
  demo_variants?: boolean
  variants?: ProductVariant[]
  stock: number
  created_at: string | null
  updated_at: string | null
}

export interface ProductPage {
  data: Product[]
  meta: {
    current_page: number
    last_page: number
    total: number
    from: number | null
    to: number | null
  }
}
