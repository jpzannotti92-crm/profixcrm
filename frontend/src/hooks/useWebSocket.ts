import { useEffect, useRef, useState, useCallback } from 'react'

interface WebSocketMessage {
  type: string
  data: any
  timestamp: number
}

interface UseWebSocketOptions {
  url: string
  onMessage?: (message: WebSocketMessage) => void
  onConnect?: () => void
  onDisconnect?: () => void
  onError?: (error: Event) => void
  reconnectInterval?: number
  maxReconnectAttempts?: number
}

export function useWebSocket({
  url,
  onMessage,
  onConnect,
  onDisconnect,
  onError,
  reconnectInterval = 3000,
  maxReconnectAttempts = 5
}: UseWebSocketOptions) {
  const [isConnected, setIsConnected] = useState(false)
  const [connectionStatus, setConnectionStatus] = useState<'connecting' | 'connected' | 'disconnected' | 'error'>('disconnected')
  const [reconnectAttempts, setReconnectAttempts] = useState(0)
  
  const wsRef = useRef<WebSocket | null>(null)
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null)
  const shouldReconnectRef = useRef(true)

  const connect = useCallback(() => {
    if (wsRef.current?.readyState === WebSocket.OPEN) {
      return
    }

    setConnectionStatus('connecting')
    
    try {
      // Para desarrollo, simular WebSocket con polling
      if (url.includes('localhost') || url.includes('127.0.0.1')) {
        console.log('🔄 Simulando WebSocket con polling para desarrollo')
        simulateWebSocketWithPolling()
        return
      }

      const ws = new WebSocket(url)
      wsRef.current = ws

      ws.onopen = () => {
        console.log('✅ WebSocket conectado')
        setIsConnected(true)
        setConnectionStatus('connected')
        setReconnectAttempts(0)
        onConnect?.()
      }

      ws.onmessage = (event) => {
        try {
          const message: WebSocketMessage = JSON.parse(event.data)
          onMessage?.(message)
        } catch (error) {
          console.error('Error parsing WebSocket message:', error)
        }
      }

      ws.onclose = () => {
        console.log('❌ WebSocket desconectado')
        setIsConnected(false)
        setConnectionStatus('disconnected')
        onDisconnect?.()
        
        if (shouldReconnectRef.current && reconnectAttempts < maxReconnectAttempts) {
          scheduleReconnect()
        }
      }

      ws.onerror = (error) => {
        console.error('❌ Error en WebSocket:', error)
        setConnectionStatus('error')
        onError?.(error)
      }

    } catch (error) {
      console.error('Error creando WebSocket:', error)
      setConnectionStatus('error')
    }
  }, [url, onMessage, onConnect, onDisconnect, onError, reconnectAttempts, maxReconnectAttempts])

  const simulateWebSocketWithPolling = useCallback(() => {
    console.log('📡 Iniciando simulación WebSocket con polling')
    setIsConnected(true)
    setConnectionStatus('connected')
    onConnect?.()

    // Simular actualizaciones de precios cada segundo
    const priceInterval = setInterval(() => {
      if (!shouldReconnectRef.current) return

      const symbols = ['EURUSD', 'GBPUSD', 'USDJPY', 'AUDUSD', 'USDCAD']
      const updates: any = {}

      symbols.forEach(symbol => {
        const basePrice = symbol === 'EURUSD' ? 1.0850 : 
                         symbol === 'GBPUSD' ? 1.2650 :
                         symbol === 'USDJPY' ? 149.25 :
                         symbol === 'AUDUSD' ? 0.6750 : 1.3450

        const change = (Math.random() - 0.5) * 0.0002
        const price = basePrice + change
        const spread = 0.0001

        updates[symbol] = {
          bid: +(price - spread/2).toFixed(5),
          ask: +(price + spread/2).toFixed(5),
          spread: spread,
          change: +(change * 100).toFixed(2),
          timestamp: Date.now()
        }
      })

      onMessage?.({
        type: 'price_update',
        data: updates,
        timestamp: Date.now()
      })
    }, 1000)

    // Simular nuevas velas cada 5 segundos
    const candleInterval = setInterval(() => {
      if (!shouldReconnectRef.current) return

      const symbols = ['EURUSD', 'GBPUSD', 'USDJPY', 'AUDUSD', 'USDCAD']
      symbols.forEach(symbol => {
        const basePrice = symbol === 'EURUSD' ? 1.0850 : 
                         symbol === 'GBPUSD' ? 1.2650 :
                         symbol === 'USDJPY' ? 149.25 :
                         symbol === 'AUDUSD' ? 0.6750 : 1.3450

        const open = basePrice + (Math.random() - 0.5) * 0.001
        const volatility = Math.random() * 0.0005
        const high = open + volatility
        const low = open - volatility
        const close = low + (high - low) * Math.random()

        onMessage?.({
          type: 'candle_update',
          data: {
            symbol,
            timeframe: 'M1',
            candle: {
              time: Date.now(),
              open: +open.toFixed(5),
              high: +high.toFixed(5),
              low: +low.toFixed(5),
              close: +close.toFixed(5),
              volume: Math.floor(Math.random() * 5000) + 100
            }
          },
          timestamp: Date.now()
        })
      })
    }, 5000)

    // Cleanup function
    return () => {
      clearInterval(priceInterval)
      clearInterval(candleInterval)
    }
  }, [onMessage, onConnect])

  const scheduleReconnect = useCallback(() => {
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current)
    }

    reconnectTimeoutRef.current = setTimeout(() => {
      console.log(`🔄 Reintentando conexión (${reconnectAttempts + 1}/${maxReconnectAttempts})`)
      setReconnectAttempts(prev => prev + 1)
      connect()
    }, reconnectInterval)
  }, [connect, reconnectInterval, reconnectAttempts, maxReconnectAttempts])

  const disconnect = useCallback(() => {
    shouldReconnectRef.current = false
    
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current)
    }

    if (wsRef.current) {
      wsRef.current.close()
      wsRef.current = null
    }

    setIsConnected(false)
    setConnectionStatus('disconnected')
  }, [])

  const sendMessage = useCallback((message: any) => {
    if (wsRef.current?.readyState === WebSocket.OPEN) {
      wsRef.current.send(JSON.stringify(message))
    } else {
      console.warn('WebSocket no está conectado')
    }
  }, [])

  useEffect(() => {
    connect()

    return () => {
      shouldReconnectRef.current = false
      disconnect()
    }
  }, [connect, disconnect])

  return {
    isConnected,
    connectionStatus,
    reconnectAttempts,
    connect,
    disconnect,
    sendMessage
  }
}
