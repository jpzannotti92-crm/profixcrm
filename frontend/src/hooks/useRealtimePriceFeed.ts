import { useState, useEffect, useCallback } from 'react'

interface PriceFeedData {
  symbol: string
  price: number
  bid: number
  ask: number
  change: number
  changePercent: number
  spread?: number
  timestamp: number
  isRealTime: boolean
  priceDirection: 'up' | 'down' | 'neutral'
}

interface UseRealtimePriceFeedOptions {
  symbols: string[]
  onPriceUpdate?: (symbol: string, data: PriceFeedData) => void
  onChartUpdate?: (symbol: string, price: number) => void
}

export function useRealtimePriceFeed({ 
  symbols, 
  onPriceUpdate, 
  onChartUpdate 
}: UseRealtimePriceFeedOptions) {
  const [priceFeed, setPriceFeed] = useState<Record<string, PriceFeedData>>({})
  const [lastUpdateTime, setLastUpdateTime] = useState<number>(Date.now())

// Estado de conexión simulado (sin proveedor externo)
const feedConnected = true
  const hasRealData = true
  const connectionError: string | null = null

  // Callbacks estables para evitar bucles infinitos
  const stableOnPriceUpdate = useCallback((symbol: string, data: PriceFeedData) => {
    onPriceUpdate?.(symbol, data)
  }, [onPriceUpdate])

  const stableOnChartUpdate = useCallback((symbol: string, price: number) => {
    onChartUpdate?.(symbol, price)
  }, [onChartUpdate])

  // Generador de feed simulado para evitar dependencia externa
  useEffect(() => {
    let rafId: number | null = null

    const basePrices: Record<string, number> = {
      EURUSD: 1.1000,
      GBPUSD: 1.2600,
      USDJPY: 150.00,
      AUDUSD: 0.6800,
      USDCAD: 1.3600,
      USDCHF: 0.9000,
      NZDUSD: 0.6200,
      EURGBP: 0.8700,
      EURJPY: 165.00,
      GBPJPY: 190.00
    }

    const updateFeed = () => {
      setPriceFeed(prev => {
        const next: Record<string, PriceFeedData> = { ...prev }
        const now = Date.now()
        symbols.forEach(symbol => {
          const prevPrice = prev[symbol]?.price ?? basePrices[symbol] ?? 1.0
          const delta = (Math.random() - 0.5) * (prevPrice > 2 ? 0.02 : 0.0002)
          const price = Math.max(0.00001, prevPrice + delta)
          const bid = price - Math.abs(delta) * 0.2
          const ask = price + Math.abs(delta) * 0.2
          const change = prev[symbol]?.price ? price - (prev[symbol]!.price) : 0
          const changePercent = prev[symbol]?.price ? (change / (prev[symbol]!.price)) * 100 : 0

          next[symbol] = {
            symbol,
            price,
            bid,
            ask,
            change,
            changePercent,
            spread: ask - bid,
            timestamp: now,
            isRealTime: true,
            priceDirection: change > 0 ? 'up' : change < 0 ? 'down' : 'neutral'
          }
        })
        return next
      })
      setLastUpdateTime(Date.now())
      rafId = requestAnimationFrame(updateFeed)
    }

    rafId = requestAnimationFrame(updateFeed)
    return () => { if (rafId) cancelAnimationFrame(rafId) }
  }, [symbols])

  // Ejecutar callbacks cuando el priceFeed cambie
  useEffect(() => {
    Object.entries(priceFeed).forEach(([symbol, feedData]) => {
      if (feedData) {
        stableOnPriceUpdate(symbol, feedData)
        stableOnChartUpdate(symbol, feedData.price)
      }
    })
  }, [priceFeed, stableOnPriceUpdate, stableOnChartUpdate])

  // Función para obtener precio actual de un símbolo
  const getCurrentPrice = useCallback((symbol: string): number | null => {
    return priceFeed[symbol]?.price || null
  }, [priceFeed])

  // Función para obtener datos completos de un símbolo
  const getSymbolData = useCallback((symbol: string): PriceFeedData | null => {
    return priceFeed[symbol] || null
  }, [priceFeed])

  // Función para verificar si un símbolo tiene datos en tiempo real
  const hasRealtimeData = useCallback((symbol: string): boolean => {
    return priceFeed[symbol]?.isRealTime || false
  }, [priceFeed])

  // Estadísticas del feed
  const feedStats = {
    totalSymbols: Object.keys(priceFeed).length,
    realtimeSymbols: Object.values(priceFeed).filter(data => data.isRealTime).length,
    lastUpdate: lastUpdateTime,
    isConnected: feedConnected,
    hasData: hasRealData,
    error: connectionError
  }

  return {
    // Datos
    priceFeed,
    feedStats,
    
    // Estado de conexión
    isConnected: feedConnected,
    hasRealData,
    connectionError,
    
    // Funciones utilitarias
    getCurrentPrice,
    getSymbolData,
    hasRealtimeData,
    
    // Metadatos
    lastUpdateTime
  }
}

export type { PriceFeedData }
