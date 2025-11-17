import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

const Dashboard = () => {
  const [stats, setStats] = useState(null)
  const [upcomingBookings, setUpcomingBookings] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchDashboard()
  }, [])

  const fetchDashboard = async () => {
    try {
      const response = await api.get('/dashboard')
      if (response.data.success) {
        setStats(response.data.stats || {})
        setUpcomingBookings(response.data.upcoming_bookings || [])
      }
    } catch (error) {
      console.error('Error fetching dashboard:', error)
      setStats({})
      setUpcomingBookings([])
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return <div className="text-center py-12">Loading...</div>
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold mb-8">Dashboard</h1>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div className="card">
          <h3 className="text-gray-600 mb-2">Total Bookings</h3>
          <p className="text-3xl font-bold text-primary-600">{stats?.total_bookings || 0}</p>
        </div>
        <div className="card">
          <h3 className="text-gray-600 mb-2">Upcoming</h3>
          <p className="text-3xl font-bold text-green-600">{stats?.upcoming_bookings || 0}</p>
        </div>
        <div className="card">
          <h3 className="text-gray-600 mb-2">Pending</h3>
          <p className="text-3xl font-bold text-yellow-600">{stats?.pending_bookings || 0}</p>
        </div>
        <div className="card">
          <h3 className="text-gray-600 mb-2">Total Spent</h3>
          <p className="text-3xl font-bold text-blue-600">${stats?.total_spent?.toFixed(2) || '0.00'}</p>
        </div>
      </div>

      {/* Upcoming Bookings */}
      <div className="card">
        <h2 className="text-2xl font-semibold mb-4">Upcoming Bookings</h2>
        {upcomingBookings.length === 0 ? (
          <p className="text-gray-500">No upcoming bookings</p>
        ) : (
          <div className="space-y-4">
            {upcomingBookings.map((booking) => (
              <div key={booking.id} className="border p-4 rounded-lg">
                <div className="flex justify-between items-start">
                  <div>
                    <h3 className="font-semibold">{booking.package?.title}</h3>
                    <p className="text-gray-600">Travel Date: {new Date(booking.travel_date).toLocaleDateString()}</p>
                    <p className="text-gray-600">Booking #: {booking.booking_number}</p>
                  </div>
                  <Link to={`/bookings/${booking.id}`} className="btn btn-primary">
                    View Details
                  </Link>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default Dashboard

