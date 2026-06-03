import request from '@/api'

// 字典项缓存（页面生命周期内有效）
const cache = {}

/**
 * 批量加载字典项
 * @param  {...string} codes 字典类型编码，如 'gender', 'status'
 * @return {Promise<Object>}  { gender: [{label, value}], status: [...] }
 */
export async function loadDicts(...codes) {
  const uncached = codes.filter(c => !cache[c])
  if (uncached.length === 0) {
    const result = {}
    codes.forEach(c => { result[c] = cache[c] })
    return result
  }

  const res = await request.get('/dict_data/items', { params: { codes: uncached.join(',') } })
  if (res.code === 0 && res.data) {
    Object.keys(res.data).forEach(code => {
      cache[code] = res.data[code]
    })
  }

  const result = {}
  codes.forEach(c => { result[c] = cache[c] || [] })
  return result
}

/**
 * 将字典项列表转换为 {value: label} 映射
 * @param  {Array}  items [{label, value}]
 * @return {Object}       {value: label}
 */
export function dictMap(items) {
  const map = {}
  if (items) {
    items.forEach(item => { map[item.value] = item.label })
  }
  return map
}
