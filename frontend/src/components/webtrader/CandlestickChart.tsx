import { useEffect, useRef, useState } from 'react'
import { 
  ChartBarIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  ClockIcon
} from '@heroicons/react/24/outline'

interface CandleData {
  timestamp: number
  open: number
  high: number
  low: number
  close: number
  volume: number
}

interface CandlestickChartProps {
  symbol: string
  data?: CandleData[]
  height?: number
  showVolume?: boolean
  timeframe?: '1m' | '5m' | '15m' | '1h' | '4h' | '1d'
}

export default function CandlestickChart({ 
  symbol, 
  height = 400, 
  showVolume = true,
  timeframe = '1m'
}: CandlestickChartProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const [candleData, setCandleData] = useState<CandleData[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [currentPrice, setCurrentPrice] = useState<number>(0)
  const [priceChange, setPriceChange] = useState<number>(0)

  // Generar datos de velas simulados para demostración
  const generateMockData = (count: number = 100): CandleData[] => {
    const data: CandleData[] = []
    let basePrice = 1.18450 // Precio base para EURUSD
    const now = Date.now()
    
    for (let i = count; i >= 0; i--) {
      const timestamp = now - (i * 60000) // 1 minuto por vela
      
      // Simular movimiento de precio realista
      const volatility = 0.0001 + Math.random() * 0.0002
      const trend = (Math.random() - 0.5) * 0.0001
      
      const open = basePrice
      const change = (Math.random() - 0.5) * volatility
      const close = open + change + trend
      
      const high = Math.max(open, close) + Math.random() * volatility * 0.5
      const low = Math.min(open, close) - Math.random() * volatility * 0.5
      
      const volume = 1000 + Math.random() * 5000
      
      data.push({
        timestamp,
        open,
        high,
        low,
        close,
        volume
      })
      
      basePrice = close
    }
    
    return data
  }

  // Actualizar datos en tiempo real
  useEffect(() => {
    const mockData = generateMockData()
    setCandleData(mockData)
    setCurrentPrice(mockData[mockData.length - 1]?.close || 0)
    setIsLoading(false)

    // Simular actualizaciones en tiempo real
    const interval = setInterval(() => {
      setCandleData(prevData => {
        const newData = [...prevData]
        const lastCandle = newData[newData.length - 1]
        
        if (lastCandle) {
          // Actualizar la última vela
          const volatility = 0.00005
          const change = (Math.random() - 0.5) * volatility
          const newClose = lastCandle.close + change
          
          lastCandle.close = newClose
          lastCandle.high = Math.max(lastCandle.high, newClose)
          lastCandle.low = Math.min(lastCandle.low, newClose)
          lastCandle.volume += Math.random() * 100
          
          setCurrentPrice(newClose)
          setPriceChange(newClose - lastCandle.open)
        }
        
        return newData
      })
    }, 1000)

    return () => clearInterval(interval)
  }, [symbol])

  // Dibujar el gráfico
  useEffect(() => {
    if (!canvasRef.current || candleData.length === 0) return

    const canvas = canvasRef.current
    const ctx = canvas.getContext('2d')
    if (!ctx) return

    // Configurar canvas
    const dpr = window.devicePixelRatio || 1
    const rect = canvas.getBoundingClientRect()
    
    canvas.width = rect.width * dpr
    canvas.height = rect.height * dpr
    
    ctx.scale(dpr, dpr)
    canvas.style.width = rect.width + 'px'
    canvas.style.height = rect.height + 'px'

    // Limpiar canvas
    ctx.clearRect(0, 0, rect.width, rect.height)

    // Configurar área de dibujo
    const padding = 40
    const chartWidth = rect.width - padding * 2
    const volumeHeight = showVolume ? 80 : 0
    const chartHeight = rect.height - padding * 2 - volumeHeight
    
    // Calcular rangos de precios
    const prices = candleData.flatMap(d => [d.high, d.low])
    const minPrice = Math.min(...prices)
    const maxPrice = Math.max(...prices)
    const priceRange = maxPrice - minPrice
    const priceBuffer = priceRange * 0.1

    // Función para convertir precio a coordenada Y
    const priceToY = (price: number) => {
      return padding + ((maxPrice + priceBuffer - price) / (priceRange + priceBuffer * 2)) * chartHeight
    }

    // Función para convertir índice a coordenada X
    const indexToX = (index: number) => {
      return padding + (index / (candleData.length - 1)) * chartWidth
    }

    // Dibujar grid
    ctx.strokeStyle = '#374151'
    ctx.lineWidth = 0.5
    
    // Grid horizontal (precios)
    for (let i = 0; i <= 5; i++) {
      const price = minPrice + (priceRange * i / 5)
      const y = priceToY(price)
      
      ctx.beginPath()
      ctx.moveTo(padding, y)
      ctx.lineTo(rect.width - padding, y)
      ctx.stroke()
      
      // Etiquetas de precio
      ctx.fillStyle = '#9CA3AF'
      ctx.font = '10px Inter'
      ctx.textAlign = 'right'
      ctx.fillText(price.toFixed(5), padding - 5, y + 3)
    }

    // Grid vertical (tiempo)
    for (let i = 0; i <= 5; i++) {
      const x = padding + (chartWidth * i / 5)
      
      ctx.beginPath()
      ctx.moveTo(x, padding)
      ctx.lineTo(x, padding + chartHeight)
      ctx.stroke()
      
      // Etiquetas de tiempo
      if (i < candleData.length) {
        const dataIndex = Math.floor((candleData.length - 1) * i / 5)
        const timestamp = candleData[dataIndex]?.timestamp
        if (timestamp) {
          const time = new Date(timestamp).toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit' 
          })
          ctx.fillStyle = '#9CA3AF'
          ctx.font = '10px Inter'
          ctx.textAlign = 'center'
          ctx.fillText(time, x, rect.height - 10)
        }
      }
    }

    // Dibujar velas
    const candleWidth = Math.max(2, chartWidth / candleData.length * 0.8)
    
    candleData.forEach((candle, index) => {
      const x = indexToX(index)
      const openY = priceToY(candle.open)
      const closeY = priceToY(candle.close)
      const highY = priceToY(candle.high)
      const lowY = priceToY(candle.low)
      
      const isBullish = candle.close > candle.open
      const color = isBullish ? '#10B981' : '#EF4444'
      
      // Dibujar mecha (high-low line)
      ctx.strokeStyle = color
      ctx.lineWidth = 1
      ctx.beginPath()
      ctx.moveTo(x, highY)
      ctx.lineTo(x, lowY)
      ctx.stroke()
      
      // Dibujar cuerpo de la vela
      ctx.fillStyle = color
      const bodyHeight = Math.abs(closeY - openY)
      const bodyY = Math.min(openY, closeY)
      
      if (bodyHeight < 1) {
        // Doji - línea horizontal
        ctx.fillRect(x - candleWidth / 2, bodyY, candleWidth, 1)
      } else {
        // Vela normal
        if (isBullish) {
          ctx.fillRect(x - candleWidth / 2, bodyY, candleWidth, bodyHeight)
        } else {
          ctx.fillRect(x - candleWidth / 2, bodyY, candleWidth, bodyHeight)
        }
      }
    })

    // Dibujar volumen si está habilitado
    if (showVolume && candleData.length > 0) {
      const maxVolume = Math.max(...candleData.map(d => d.volume))
      const volumeY = rect.height - volumeHeight - 20
      
      candleData.forEach((candle, index) => {
        const x = indexToX(index)
        const volumeBarHeight = (candle.volume / maxVolume) * (volumeHeight - 10)
        const isBullish = candle.close > candle.open
        
        ctx.fillStyle = isBullish ? '#10B98150' : '#EF444450'
        ctx.fillRect(x - candleWidth / 2, volumeY - volumeBarHeight, candleWidth, volumeBarHeight)
      })
      
      // Etiqueta de volumen
      ctx.fillStyle = '#9CA3AF'
      ctx.font = '10px Inter'
      ctx.textAlign = 'left'
      ctx.fillText('Volumen', padding, volumeY + 15)
    }

    // Dibujar línea de precio actual
    if (currentPrice > 0) {
      const currentY = priceToY(currentPrice)
      ctx.strokeStyle = priceChange >= 0 ? '#10B981' : '#EF4444'
      ctx.lineWidth = 2
      ctx.setLineDash([5, 5])
      
      ctx.beginPath()
      ctx.moveTo(padding, currentY)
      ctx.lineTo(rect.width - padding, currentY)
      ctx.stroke()
      
      ctx.setLineDash([])
      
      // Etiqueta de precio actual
      ctx.fillStyle = priceChange >= 0 ? '#10B981' : '#EF4444'
      ctx.fillRect(rect.width - padding - 60, currentY - 10, 55, 20)
      
      ctx.fillStyle = 'white'
      ctx.font = 'bold 10px Inter'
      ctx.textAlign = 'center'
      ctx.fillText(currentPrice.toFixed(5), rect.width - padding - 32.5, currentY + 3)
    }

  }, [candleData, showVolume, currentPrice, priceChange])

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-96 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
        <div className="text-center">
          <ChartBarIcon className="w-12 h-12 text-secondary-400 mx-auto mb-4 animate-pulse" />
          <p className="text-secondary-600 dark:text-secondary-400">Cargando gráfico...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="bg-white dark:bg-secondary-800 rounded-lg border border-secondary-200 dark:border-secondary-700 overflow-hidden">
      {/* Header */}
      <div className="p-4 border-b border-secondary-200 dark:border-secondary-700">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
              {symbol}
            </h3>
            <div className="flex items-center space-x-2">
              <span className="text-2xl font-bold text-secondary-900 dark:text-white">
                {currentPrice.toFixed(5)}
              </span>
              <div className={`flex items-center space-x-1 px-2 py-1 rounded text-sm font-medium ${
                priceChange >= 0 
                  ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
                  : 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400'
              }`}>
                {priceChange >= 0 ? (
                  <ArrowTrendingUpIcon className="w-4 h-4" />
                ) : (
                  <ArrowTrendingDownIcon className="w-4 h-4" />
                )}
                <span>{priceChange >= 0 ? '+' : ''}{(priceChange * 10000).toFixed(1)} pips</span>
              </div>
            </div>
          </div>
          
          <div className="flex items-center space-x-2">
            {/* Timeframe selector */}
            <div className="flex items-center space-x-1 bg-secondary-100 dark:bg-secondary-700 rounded-lg p-1">
              {['1m', '5m', '15m', '1h', '4h', '1d'].map((tf) => (
                <button
                  key={tf}
                  className={`px-3 py-1 text-xs font-medium rounded transition-colors ${
                    timeframe === tf
                      ? 'bg-primary-600 text-white'
                      : 'text-secondary-600 dark:text-secondary-400 hover:text-secondary-900 dark:hover:text-white'
                  }`}
                >
                  {tf}
                </button>
              ))}
            </div>
            
            <div className="flex items-center space-x-1 text-xs text-secondary-500 dark:text-secondary-400">
              <ClockIcon className="w-4 h-4" />
              <span>Tiempo real</span>
            </div>
          </div>
        </div>
      </div>

      {/* Chart */}
      <div className="relative">
        <canvas
          ref={canvasRef}
          className="w-full"
          style={{ height: `${height}px` }}
        />
        
        {/* Live indicator */}
        <div className="absolute top-4 right-4 flex items-center space-x-2 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-medium">
          <div className="w-2 h-2 bg-white rounded-full animate-pulse"></div>
          <span>EN VIVO</span>
        </div>
      </div>

      {/* Chart controls */}
      <div className="p-4 border-t border-secondary-200 dark:border-secondary-700 bg-secondary-50 dark:bg-secondary-800/50">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <button className="flex items-center space-x-2 text-sm text-secondary-600 dark:text-secondary-400 hover:text-secondary-900 dark:hover:text-white transition-colors">
              <ChartBarIcon className="w-4 h-4" />
              <span>Indicadores</span>
            </button>
            
            <label className="flex items-center space-x-2 text-sm text-secondary-600 dark:text-secondary-400">
              <input
                type="checkbox"
                checked={showVolume}
                onChange={(_) => {}}
                className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
              />
              <span>Mostrar volumen</span>
            </label>
          </div>
          
          <div className="text-xs text-secondary-500 dark:text-secondary-400">
            Última actualización: {new Date().toLocaleTimeString('es-ES')}
          </div>
        </div>
      </div>
    </div>
  )
}
