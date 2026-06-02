import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

type Product = {
  id: number
  code: string
  amount: number
  currency: string
  uses: number
}

// simple in-memory cache
const productCache = new Map<
  string,
  { data: Product; timestamp: number }
>()

const TTL = 60 * 1000 // 1 minute

export async function getProductByCode(code: string): Promise<Product> {
  const now = Date.now()

  const cached = productCache.get(code)

  // return cached if fresh
  if (cached && now - cached.timestamp < TTL) {
    return cached.data
  }

  // fetch from backend
  const { data } = await axios.get(
    generateOcsUrl(`/apps/libresign/api/v1/product/by-code?code=${code}`),
    { timeout: 10000 }
  )

  const product = data?.ocs?.data?.product

  console.log('[Product]:', product)

  // store in cache
  productCache.set(code, {
    data: product,
    timestamp: now,
  })

  return product
}
