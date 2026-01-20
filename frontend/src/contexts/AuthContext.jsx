import { createContext, useContext, useState, useEffect } from 'react'
import api, { persistTokens, clearTokens } from '../services/api'

const AuthContext = createContext(null)

export const useAuth = () => {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return context
}

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    bootstrapAuth()
  }, [])

  const bootstrapAuth = async () => {
    const token = localStorage.getItem('token')
    const refreshToken = localStorage.getItem('refresh_token')

    if (token) {
      persistTokens(token, refreshToken)
      await fetchUser()
      return
    }

    if (refreshToken) {
      try {
        await refreshSession(refreshToken)
        await fetchUser()
        return
      } catch (error) {
        console.error('Refresh on bootstrap failed:', error)
        clearTokens()
      }
    }

    setLoading(false)
  }

  const fetchUser = async () => {
    try {
      const response = await api.get('/user')
      if (response.data.success && response.data.user) {
        setUser(response.data.user)
      } else {
        throw new Error('Invalid response')
      }
    } catch (error) {
      console.error('Error fetching user:', error)
      clearTokens()
      setUser(null)
    } finally {
      setLoading(false)
    }
  }

  const login = async (email, password) => {
    const response = await api.post('/login', { email, password })
    if (response.data.success) {
      const { token, refresh_token, user } = response.data
      persistTokens(token, refresh_token)
      setUser(user)
      return response.data
    }
    throw new Error(response.data.message || 'Login failed')
  }

  const register = async (userData) => {
    const response = await api.post('/register', userData)
    if (response.data.success) {
      const { token, refresh_token, user } = response.data
      persistTokens(token, refresh_token)
      setUser(user)
      return response.data
    }
    throw new Error(response.data.message || 'Registration failed')
  }

  const refreshSession = async (refreshToken) => {
    const response = await api.post('/refresh', { refresh_token: refreshToken })
    if (response.data.success) {
      const { token, refresh_token, user } = response.data
      persistTokens(token, refresh_token)
      if (user) setUser(user)
      return response.data
    }
    throw new Error(response.data.message || 'Refresh failed')
  }

  const logout = async () => {
    const refreshToken = localStorage.getItem('refresh_token')
    try {
      await api.post('/logout', { refresh_token: refreshToken })
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      clearTokens()
      setUser(null)
    }
  }

  const value = {
    user,
    loading,
    login,
    register,
    logout,
    refreshSession,
    isAuthenticated: !!user,
    isAdmin: user?.role === 'admin',
    isAgent: user?.role === 'agent',
    isCustomer: user?.role === 'customer',
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

