import { useNavigate } from 'react-router-dom'
import { useCallback } from 'react'

export function useAuthNavigation() {
  const navigate = useNavigate()

  const navigateToLogin = useCallback(() => {
    navigate('/auth/login', { replace: true })
  }, [navigate])

  const navigateToDashboard = useCallback(() => {
    navigate('/dashboard', { replace: true })
  }, [navigate])

  return {
    navigateToLogin,
    navigateToDashboard
  }
}
