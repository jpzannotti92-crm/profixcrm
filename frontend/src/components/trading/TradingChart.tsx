import { useEffect, useRef, useState, forwardRef, useImperativeHandle } from 'react'
import { createChart, ColorType, CandlestickSeries, HistogramSeries } from 'lightweight-charts'
import { cn } from '../../utils/cn'

interface OHLCData {
  time: number
  open: number
  high: number
  low: number
  close: number
  volume?: number
}

interface TradingChartProps {
  symbol: string
  timeframe: string
  data: OHLCData[]
  className?: string
  height?: number
  onCrosshairMove?: (price: number | null) => void
}

export interface TradingChartRef {
  updatePrice: (newCandle: OHLCData) => void
  updateRealtimePrice: (price: number) => void
  addRealtimeCandle: (price: number, volume?: number) => void
}

const TradingChart = forwardRef<TradingChartRef, TradingChartProps>(({ 
  symbol, 
  timeframe, 
  data, 
  className, 
  height = 400,
  onCrosshairMove 
}, ref) => {
  const chartContainerRef = useRef<HTMLDivElement>(null)
  const chartRef = useRef<any>(null)
  const candlestickSeriesRef = useRef<any>(null)
  const volumeSeriesRef = useRef<any>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [currentCandle, setCurrentCandle] = useState<OHLCData | null>(null)
  const [realtimePrice, setRealtimePrice] = useState<number | null>(null)

  // Exponer métodos al componente padre
  useImperativeHandle(ref, () => ({
    updatePrice: (newCandle: OHLCData) => {
      updateLastCandle(newCandle)
    },
    updateRealtimePrice: (price: number) => {
      updateRealtimePriceOnChart(price)
    },
    addRealtimeCandle: (price: number, volume?: number) => {
      addRealtimeCandleToChart(price, volume)
    }
  }))

  // Actualizar precio en tiempo real en la vela actual
  const updateRealtimePriceOnChart = (price: number) => {
    if (!candlestickSeriesRef.current || !data.length) return

    try {
      setRealtimePrice(price)
      
      // Obtener la última vela de los datos
      const lastCandle = data[data.length - 1]
      if (!lastCandle) return

      // Calcular el tiempo para la vela actual basado en el timeframe
      const getTimeframeSeconds = (tf: string) => {
        switch (tf) {
          case 'M1': return 60
          case 'M5': return 300
          case 'M15': return 900
          case 'M30': return 1800
          case 'H1': return 3600
          case 'H4': return 14400
          case 'D1': return 86400
          default: return 300
        }
      }

      const timeframeSeconds = getTimeframeSeconds(timeframe)
      const currentTime = Math.floor(Date.now() / 1000)
      const currentCandleTime = Math.floor(currentTime / timeframeSeconds) * timeframeSeconds

      // Si es una nueva vela (tiempo diferente), crear nueva vela
      if (currentCandleTime > lastCandle.time) {
        const newCandle = {
          time: currentCandleTime,
          open: price,
          high: price,
          low: price,
          close: price
        }
        
        setCurrentCandle(newCandle)
        candlestickSeriesRef.current.update(newCandle)
        
        console.log('📊 Nueva vela creada:', {
          symbol,
          time: new Date(currentCandleTime * 1000).toISOString(),
          price,
          candle: newCandle
        })
      } else {
        // Actualizar la vela actual
        const updatedCandle = {
          time: currentCandle?.time || currentCandleTime,
          open: currentCandle?.open || lastCandle.close,
          high: Math.max(currentCandle?.high || lastCandle.close, price),
          low: Math.min(currentCandle?.low || lastCandle.close, price),
          close: price
        }

        setCurrentCandle(updatedCandle)
        candlestickSeriesRef.current.update(updatedCandle)
        
        console.log('📊 Vela actualizada:', {
          symbol,
          price,
          candle: updatedCandle
        })
      }
    } catch (err) {
      console.error('Error actualizando precio en tiempo real:', err)
    }
  }

  // Agregar nueva vela basada en precio en tiempo real
  const addRealtimeCandleToChart = (price: number, volume?: number) => {
    if (!candlestickSeriesRef.current) return

    try {
      const now = Math.floor(Date.now() / 1000)
      
      // Crear nueva vela con el precio actual
      const newCandle = {
        time: now,
        open: realtimePrice || price,
        high: price,
        low: price,
        close: price
      }

      setCurrentCandle(newCandle)
      candlestickSeriesRef.current.update(newCandle)

      // Actualizar volumen si está disponible
      if (volume && volumeSeriesRef.current) {
        volumeSeriesRef.current.update({
          time: now,
          value: volume,
          color: '#10b98150'
        })
      }

      console.log('📊 Nueva vela agregada:', newCandle)
    } catch (err) {
      console.error('Error agregando vela en tiempo real:', err)
    }
  }

  // Agregar nueva vela en tiempo real (método original)
  const updateLastCandle = (newData: OHLCData) => {
    if (!candlestickSeriesRef.current) return

    try {
      const candleData = {
        time: Math.floor(newData.time),
        open: newData.open,
        high: newData.high,
        low: newData.low,
        close: newData.close,
      }

      candlestickSeriesRef.current.update(candleData)

      if (newData.volume && volumeSeriesRef.current) {
        volumeSeriesRef.current.update({
          time: Math.floor(newData.time),
          value: newData.volume,
          color: newData.close >= newData.open ? '#10b98150' : '#ef444450',
        })
      }
    } catch (err) {
      console.error('Error actualizando vela:', err)
    }
  }

  // Crear el gráfico
  useEffect(() => {
    if (!chartContainerRef.current) return

    try {
      const chart = createChart(chartContainerRef.current, {
        width: chartContainerRef.current.clientWidth,
        height: height,
        layout: {
          background: { type: ColorType.Solid, color: 'transparent' },
          textColor: '#d1d5db',
        },
        grid: {
          vertLines: { color: '#374151' },
          horzLines: { color: '#374151' },
        },
        crosshair: {
          mode: 1, // Normal crosshair
          vertLine: {
            width: 1,
            color: '#6b7280',
            style: 2, // Dashed
          },
          horzLine: {
            width: 1,
            color: '#6b7280',
            style: 2, // Dashed
          },
        },
        rightPriceScale: {
          borderColor: '#374151',
          textColor: '#d1d5db',
        },
        timeScale: {
          borderColor: '#374151',
          timeVisible: true,
          secondsVisible: timeframe === 'M1' || timeframe === 'M5',
        },
        handleScroll: {
          mouseWheel: true,
          pressedMouseMove: true,
        },
        handleScale: {
          axisPressedMouseMove: true,
          mouseWheel: true,
          pinch: true,
        },
      })

      // Crear serie de candlesticks con colores dinámicos
      const candlestickSeries = chart.addSeries(CandlestickSeries, {
        upColor: '#10b981', // Verde para velas alcistas (close > open)
        downColor: '#ef4444', // Rojo para velas bajistas (close < open)
        borderDownColor: '#ef4444',
        borderUpColor: '#10b981',
        wickDownColor: '#ef4444',
        wickUpColor: '#10b981',
        // Configuración adicional para mejor visualización
        priceLineVisible: false,
        lastValueVisible: true,
      })

      // Crear serie de volumen
      const volumeSeries = chart.addSeries(HistogramSeries, {
        color: '#6b7280',
        priceFormat: {
          type: 'volume',
        },
        priceScaleId: 'volume',
      })

      // Configurar escala de volumen
      chart.priceScale('volume').applyOptions({
        scaleMargins: {
          top: 0.8,
          bottom: 0,
        },
      })

      // Manejar crosshair
      chart.subscribeCrosshairMove((param: any) => {
        if (onCrosshairMove) {
          const price = param.seriesPrices.get(candlestickSeries)
          onCrosshairMove(price ? price.close : null)
        }
      })

      chartRef.current = chart
      candlestickSeriesRef.current = candlestickSeries
      volumeSeriesRef.current = volumeSeries

      setError(null)
    } catch (err) {
      console.error('Error creando gráfico:', err)
      setError('Error al inicializar el gráfico')
    }

    // Cleanup
    return () => {
      if (chartRef.current) {
        chartRef.current.remove()
      }
    }
  }, [height, timeframe, onCrosshairMove])

  // Actualizar datos cuando cambien
  useEffect(() => {
    if (!candlestickSeriesRef.current || !volumeSeriesRef.current || !data.length) {
      setIsLoading(false)
      return
    }

    try {
      // Convertir datos OHLC al formato de Lightweight Charts
      const candlestickData = data
        .map(item => ({
          time: item.time, // Los datos ya vienen en segundos desde el backend
          open: item.open,
          high: item.high,
          low: item.low,
          close: item.close,
        }))
        .sort((a, b) => a.time - b.time) // Asegurar ordenamiento ascendente

      // Datos de volumen
      const volumeData = data
        .filter(item => item.volume !== undefined)
        .map(item => ({
          time: item.time, // Los datos ya vienen en segundos desde el backend
          value: item.volume!,
          color: item.close >= item.open ? '#10b98150' : '#ef444450', // Semi-transparent
        }))
        .sort((a, b) => a.time - b.time) // Asegurar ordenamiento ascendente

      // Actualizar series
      candlestickSeriesRef.current.setData(candlestickData)
      if (volumeData.length > 0) {
        volumeSeriesRef.current.setData(volumeData)
      }

      // Ajustar vista a los datos
      chartRef.current?.timeScale().fitContent()
      
      setIsLoading(false)
      setError(null)
    } catch (err) {
      console.error('Error actualizando datos del gráfico:', err)
      setError('Error al cargar los datos del gráfico')
      setIsLoading(false)
    }
  }, [data])

  // Manejar redimensionamiento
  useEffect(() => {
    const handleResize = () => {
      if (chartRef.current && chartContainerRef.current) {
        chartRef.current.applyOptions({
          width: chartContainerRef.current.clientWidth,
        })
      }
    }

    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [])

  // Exponer método para actualizaciones externas
  useEffect(() => {
    (window as any).updateTradingChart = updateLastCandle
  }, [])

  if (error) {
    return (
      <div className={cn('relative bg-secondary-900 rounded-lg overflow-hidden flex items-center justify-center', className)}>
        <div className="text-center text-white">
          <div className="text-red-500 mb-2">⚠️</div>
          <div className="text-sm">{error}</div>
          <div className="text-xs text-secondary-400 mt-2">
            Usando gráfico simulado como respaldo
          </div>
          <div className="text-4xl font-bold mt-4">
            {data.length > 0 ? data[data.length - 1].close.toFixed(5) : '1.08500'}
          </div>
          <div className="text-sm text-secondary-400">
            {symbol} • {timeframe}
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className={cn('relative bg-secondary-900 rounded-lg overflow-hidden', className)}>
      {/* Header del gráfico */}
      <div className="absolute top-0 left-0 z-10 p-4 bg-gradient-to-r from-secondary-900/90 to-transparent">
        <div className="flex items-center space-x-4">
          <h3 className="text-lg font-semibold text-white">
            {symbol}
          </h3>
          <span className="px-2 py-1 bg-primary-600 text-white text-xs rounded">
            {timeframe}
          </span>
          {isLoading && (
            <div className="flex items-center space-x-2">
              <div className="w-2 h-2 bg-primary-500 rounded-full animate-pulse"></div>
              <span className="text-xs text-secondary-400">Cargando...</span>
            </div>
          )}
          <div className="text-xs text-green-400">
            📊 Datos • {data.length} velas
          </div>
        </div>
      </div>

      {/* Contenedor del gráfico */}
      <div 
        ref={chartContainerRef} 
        className="w-full"
        style={{ height: `${height}px` }}
      />

      {/* Overlay de carga */}
      {isLoading && (
        <div className="absolute inset-0 bg-secondary-900/50 flex items-center justify-center">
          <div className="text-center">
            <div className="w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
            <p className="text-sm text-secondary-400">Cargando datos de mercado...</p>
          </div>
        </div>
      )}
    </div>
  )
})

export default TradingChart
