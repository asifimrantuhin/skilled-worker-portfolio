import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import api from '../services/api'
import toast from 'react-hot-toast'

const BookingDetail = () => {
  const { id } = useParams()
  const [booking, setBooking] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchBooking()
  }, [id])

  const fetchBooking = async () => {
    try {
      const response = await api.get(`/bookings/${id}`)
      if (response.data.success && response.data.booking) {
        setBooking(response.data.booking)
      } else {
        toast.error('Booking not found')
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Failed to load booking')
    } finally {
      setLoading(false)
    }
  }

  const handleCancel = async () => {
    if (!window.confirm('Are you sure you want to cancel this booking?')) {
      return
    }

    try {
      await api.put(`/bookings/${id}/cancel`)
      toast.success('Booking cancelled successfully')
      fetchBooking()
    } catch (error) {
      toast.error(error.response?.data?.message || 'Failed to cancel booking')
    }
  }

  if (loading) {
    return <div className="text-center py-12">Loading...</div>
  }

  if (!booking) {
    return <div className="text-center py-12">Booking not found</div>
  }

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold mb-8">Booking Details</h1>

      <div className="card mb-6">
        <div className="flex justify-between items-start mb-4">
          <div>
            <h2 className="text-2xl font-semibold">{booking.package?.title}</h2>
            <p className="text-gray-600">Booking #: {booking.booking_number}</p>
          </div>
          <span className={`px-4 py-2 rounded-full font-medium ${
            booking.status === 'confirmed' ? 'bg-green-100 text-green-800' :
            booking.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
            booking.status === 'cancelled' ? 'bg-red-100 text-red-800' :
            'bg-blue-100 text-blue-800'
          }`}>
            {booking.status}
          </span>
        </div>

        <div className="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p className="text-gray-600">Travel Date</p>
            <p className="font-semibold">{new Date(booking.travel_date).toLocaleDateString()}</p>
          </div>
          <div>
            <p className="text-gray-600">Destination</p>
            <p className="font-semibold">{booking.package?.destination}</p>
          </div>
          <div>
            <p className="text-gray-600">Participants</p>
            <p className="font-semibold">
              {booking.adults} adults, {booking.children} children, {booking.infants} infants
            </p>
          </div>
          <div>
            <p className="text-gray-600">Payment Status</p>
            <p className="font-semibold">{booking.payment_status}</p>
          </div>
        </div>

        <div className="border-t pt-4">
          <div className="flex justify-between mb-2">
            <span>Package Price</span>
            <span>${booking.package_price}</span>
          </div>
          <div className="flex justify-between mb-2">
            <span>Tax (10%)</span>
            <span>${booking.tax}</span>
          </div>
          <div className="flex justify-between mb-2">
            <span>Discount</span>
            <span>-${booking.discount}</span>
          </div>
          <div className="flex justify-between font-bold text-lg border-t pt-2">
            <span>Total Amount</span>
            <span>${booking.total_amount}</span>
          </div>
          <div className="flex justify-between mt-2">
            <span>Paid Amount</span>
            <span>${booking.paid_amount}</span>
          </div>
        </div>
      </div>

      {booking.travelers_info && booking.travelers_info.length > 0 && (
        <div className="card mb-6">
          <h3 className="text-xl font-semibold mb-4">Traveler Information</h3>
          <div className="space-y-4">
            {booking.travelers_info.map((traveler, index) => (
              <div key={index} className="border p-4 rounded-lg">
                <h4 className="font-semibold mb-2">Traveler {index + 1}</h4>
                <p><strong>Name:</strong> {traveler.name}</p>
                <p><strong>Email:</strong> {traveler.email}</p>
                <p><strong>Phone:</strong> {traveler.phone}</p>
                {traveler.passport && <p><strong>Passport:</strong> {traveler.passport}</p>}
              </div>
            ))}
          </div>
        </div>
      )}

      {booking.special_requests && (
        <div className="card mb-6">
          <h3 className="text-xl font-semibold mb-2">Special Requests</h3>
          <p className="text-gray-600">{booking.special_requests}</p>
        </div>
      )}

      <div className="flex gap-4">
        {booking.status === 'pending' && (
          <button onClick={handleCancel} className="btn btn-danger">
            Cancel Booking
          </button>
        )}
        {booking.payment_status !== 'paid' && (
          <Link to={`/payment/${booking.id}`} className="btn btn-primary">
            Make Payment
          </Link>
        )}
      </div>
    </div>
  )
}

export default BookingDetail

