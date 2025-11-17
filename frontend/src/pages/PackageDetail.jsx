import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import toast from 'react-hot-toast'

const PackageDetail = () => {
  const { id } = useParams()
  const navigate = useNavigate()
  const { isAuthenticated } = useAuth()
  const [packageData, setPackageData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [selectedDate, setSelectedDate] = useState('')
  const [availability, setAvailability] = useState(null)
  const [checkingAvailability, setCheckingAvailability] = useState(false)

  useEffect(() => {
    fetchPackage()
  }, [id])

  const fetchPackage = async () => {
    try {
      const response = await api.get(`/packages/${id}`)
      if (response.data.success && response.data.package) {
        setPackageData(response.data.package)
      } else {
        toast.error('Package not found')
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Failed to load package')
    } finally {
      setLoading(false)
    }
  }

  const checkAvailability = async () => {
    if (!selectedDate) {
      toast.error('Please select a date')
      return
    }

    setCheckingAvailability(true)
    try {
      const response = await api.get(`/packages/${id}/availability`, {
        params: { date: selectedDate, participants: 1 }
      })
      setAvailability(response.data)
      if (response.data.available) {
        toast.success('Package is available for this date!')
      } else {
        toast.error('Package is not available for this date')
      }
    } catch (error) {
      toast.error('Failed to check availability')
    } finally {
      setCheckingAvailability(false)
    }
  }

  const handleBookNow = () => {
    if (!isAuthenticated) {
      toast.error('Please login to book')
      navigate('/login')
      return
    }
    navigate(`/booking/${id}`)
  }

  if (loading) {
    return <div className="text-center py-12">Loading...</div>
  }

  if (!packageData) {
    return <div className="text-center py-12">Package not found</div>
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Images */}
        <div>
          {packageData.images && packageData.images.length > 0 ? (
            <img 
              src={`http://localhost:8000/storage/${packageData.images[0]}`} 
              alt={packageData.title}
              className="w-full h-96 object-cover rounded-lg"
              onError={(e) => {
                e.target.src = 'https://via.placeholder.com/800x600?text=No+Image'
              }}
            />
          ) : (
            <div className="w-full h-96 bg-gray-200 rounded-lg flex items-center justify-center">
              <span className="text-gray-400">No Image Available</span>
            </div>
          )}
        </div>

        {/* Details */}
        <div>
          <h1 className="text-4xl font-bold mb-4">{packageData.title}</h1>
          <p className="text-gray-600 mb-6">{packageData.description}</p>

          <div className="card mb-6">
            <h2 className="text-2xl font-semibold mb-4">Package Details</h2>
            <div className="space-y-2">
              <p><strong>Destination:</strong> {packageData.destination}</p>
              <p><strong>Duration:</strong> {packageData.duration_days} days</p>
              <p><strong>Price:</strong> 
                <span className="text-2xl font-bold text-primary-600 ml-2">
                  ${packageData.discount_price || packageData.price}
                </span>
                {packageData.discount_price && (
                  <span className="text-gray-500 line-through ml-2">${packageData.price}</span>
                )}
              </p>
            </div>
          </div>

          {/* Availability Check */}
          <div className="card mb-6">
            <h3 className="text-xl font-semibold mb-4">Check Availability</h3>
            <div className="flex gap-4">
              <input
                type="date"
                value={selectedDate}
                onChange={(e) => setSelectedDate(e.target.value)}
                min={new Date().toISOString().split('T')[0]}
                className="input flex-1"
              />
              <button 
                onClick={checkAvailability}
                disabled={checkingAvailability}
                className="btn btn-primary"
              >
                {checkingAvailability ? 'Checking...' : 'Check'}
              </button>
            </div>
            {availability && (
              <div className={`mt-4 p-4 rounded ${availability.available ? 'bg-green-50' : 'bg-red-50'}`}>
                {availability.available ? (
                  <p className="text-green-800">Available! {availability.remaining_slots} slots remaining</p>
                ) : (
                  <p className="text-red-800">Not available for this date</p>
                )}
              </div>
            )}
          </div>

          <button onClick={handleBookNow} className="btn btn-primary w-full text-lg py-3">
            Book Now
          </button>
        </div>
      </div>

      {/* Itinerary, Inclusions, Exclusions */}
      <div className="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
        {packageData.itinerary && (
          <div className="card">
            <h3 className="text-xl font-semibold mb-4">Itinerary</h3>
            <ul className="space-y-2">
              {packageData.itinerary.map((item, index) => (
                <li key={index} className="text-gray-600">{item}</li>
              ))}
            </ul>
          </div>
        )}
        {packageData.inclusions && (
          <div className="card">
            <h3 className="text-xl font-semibold mb-4">Inclusions</h3>
            <ul className="space-y-2">
              {packageData.inclusions.map((item, index) => (
                <li key={index} className="text-gray-600">✓ {item}</li>
              ))}
            </ul>
          </div>
        )}
        {packageData.exclusions && (
          <div className="card">
            <h3 className="text-xl font-semibold mb-4">Exclusions</h3>
            <ul className="space-y-2">
              {packageData.exclusions.map((item, index) => (
                <li key={index} className="text-gray-600">✗ {item}</li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </div>
  )
}

export default PackageDetail

