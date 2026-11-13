import { useCallback, useEffect, useRef, useState } from 'react'

export function useData<T>(load: () => Promise<T>, pollMs: number | null = null) {
  const [data, setData] = useState<T | null>(null)
  const [error, setError] = useState<string | null>(null)
  const loader = useRef(load)
  loader.current = load

  const reload = useCallback(async () => {
    try {
      setData(await loader.current())
      setError(null)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao carregar')
    }
  }, [])

  useEffect(() => {
    reload()
  }, [reload])

  useEffect(() => {
    if (pollMs === null) return
    const timer = setInterval(reload, pollMs)
    return () => clearInterval(timer)
  }, [pollMs, reload])

  return { data, error, reload, setData }
}
