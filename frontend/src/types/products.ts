export interface Product {
  id: number
  name: string
  description: string
  image: string | null
  price: string
  category: string
  stock: number
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