import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

const AgentDashboard = () => {
  const [stats, setStats] = useState(null)
  const [recentBookings, setRecentBookings] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchDashboard()
  }, [])

  const fetchDashboard = async () => {
    try {
      const response = await api.get('/agent/dashboard')
      if (response.data.success) {
        setStats(response.data.stats || {})
        setRecentBookings(response.data.recent_bookings || [])
      }
    } catch (error) {
      console.error('Error fetching dashboard:', error)
      setStats({})
      setRecentBookings([])
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return <div className="text-center py-12">Loading...</div>
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold mb-8">Agent Dashboard</h1>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div className="card">
          <h3 className="text-gray-600 mb-2">Total Bookings</h3>
          <p className="text-3xl font-bold text-primary-600">{stats?.total_bookings || 0}</p>
        </div>
        <div className="card">
          <h3 className="text-gray-600 mb-2">Total Commission</h3>
          <p className="text-3xl font-bold text-green-600">${stats?.total_commission?.toFixed(2) || '0.00'}</p>
        </div>
        <div className="card">
          <h3 className="text-gray-600 mb-2">Pending Commission</h3>
          <p className="text-3xl font-bold text-yellow-600">${stats?.pending_commission?.toFixed(2) || '0.00'}</p>
        </div>
      </div>

      <div className="card">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-2xl font-semibold">Recent Bookings</h2>
          <Link to="/agent/bookings" className="btn btn-primary">
            View All
          </Link>
        </div>
        {recentBookings.length === 0 ? (
          <p className="text-gray-500">No recent bookings</p>
        ) : (
          <div className="space-y-4">
            {recentBookings.map((booking) => (
              <div key={booking.id} className="border p-4 rounded-lg">
                <div className="flex justify-between items-start">
                  <div>
                    <h3 className="font-semibold">{booking.package?.title}</h3>
                    <p className="text-gray-600">Customer: {booking.user?.name}</p>
                    <p className="text-gray-600">Travel Date: {new Date(booking.travel_date).toLocaleDateString()}</p>
                    <p className="text-gray-600">Total: ${booking.total_amount}</p>
                  </div>
                  <span className={`px-3 py-1 rounded-full text-sm ${
                    booking.status === 'confirmed' ? 'bg-green-100 text-green-800' :
                    booking.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-gray-100 text-gray-800'
                  }`}>
                    {booking.status}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default AgentDashboard

