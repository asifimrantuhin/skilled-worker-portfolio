import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

const MyBookings = () => {
  const [bookings, setBookings] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchBookings()
  }, [])

  const fetchBookings = async () => {
    try {
      const response = await api.get('/bookings')
      if (response.data.success && response.data.bookings) {
        const bookings = response.data.bookings.data || response.data.bookings
        setBookings(Array.isArray(bookings) ? bookings : [])
      } else {
        setBookings([])
      }
    } catch (error) {
      console.error('Error fetching bookings:', error)
      setBookings([])
    } finally {
      setLoading(false)
    }
  }

  const getStatusColor = (status) => {
    const colors = {
      pending: 'bg-yellow-100 text-yellow-800',
      confirmed: 'bg-green-100 text-green-800',
      cancelled: 'bg-red-100 text-red-800',
      completed: 'bg-blue-100 text-blue-800',
    }
    return colors[status] || 'bg-gray-100 text-gray-800'
  }

  if (loading) {
    return <div className="text-center py-12">Loading...</div>
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold mb-8">My Bookings</h1>

      {bookings.length === 0 ? (
        <div className="card text-center py-12">
          <p className="text-gray-500 mb-4">No bookings found</p>
          <Link to="/packages" className="btn btn-primary">
            Browse Packages
          </Link>
        </div>
      ) : (
        <div className="space-y-4">
          {bookings.map((booking) => (
            <div key={booking.id} className="card">
              <div className="flex justify-between items-start">
                <div className="flex-1">
                  <div className="flex items-center gap-4 mb-2">
                    <h3 className="text-xl font-semibold">{booking.package?.title}</h3>
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(booking.status)}`}>
                      {booking.status}
                    </span>
                  </div>
                  <p className="text-gray-600 mb-2">Booking #: {booking.booking_number}</p>
                  <p className="text-gray-600 mb-2">Travel Date: {new Date(booking.travel_date).toLocaleDateString()}</p>
                  <p className="text-gray-600 mb-2">
                    Participants: {booking.adults} adults, {booking.children} children
                  </p>
                  <p className="text-lg font-semibold">Total: ${booking.total_amount}</p>
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
  )
}

export default MyBookings

