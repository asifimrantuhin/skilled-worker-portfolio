import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import api from '../services/api'
import toast from 'react-hot-toast'

const Booking = () => {
  const { packageId } = useParams()
  const navigate = useNavigate()
  const [packageData, setPackageData] = useState(null)
  const [formData, setFormData] = useState({
    travel_date: '',
    adults: 1,
    children: 0,
    infants: 0,
    travelers_info: [{ name: '', email: '', phone: '', passport: '' }],
    special_requests: '',
  })
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    fetchPackage()
  }, [packageId])

  const fetchPackage = async () => {
    try {
      const response = await api.get(`/packages/${packageId}`)
      if (response.data.success && response.data.package) {
        setPackageData(response.data.package)
      } else {
        toast.error('Package not found')
        navigate('/packages')
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Failed to load package')
      navigate('/packages')
    }
  }

  const handleTravelerChange = (index, field, value) => {
    const travelers = [...formData.travelers_info]
    travelers[index][field] = value
    setFormData({ ...formData, travelers_info: travelers })
  }

  const addTraveler = () => {
    setFormData({
      ...formData,
      travelers_info: [...formData.travelers_info, { name: '', email: '', phone: '', passport: '' }]
    })
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      const response = await api.post('/bookings', {
        package_id: packageId,
        ...formData,
      })
      if (response.data.success) {
        toast.success('Booking created successfully!')
        navigate(`/bookings/${response.data.booking.id}`)
      } else {
        toast.error(response.data.message || 'Failed to create booking')
      }
    } catch (error) {
      const errorMessage = error.response?.data?.message || 
                          (error.response?.data?.errors ? 
                            Object.values(error.response.data.errors).flat().join(', ') : 
                            'Failed to create booking')
      toast.error(errorMessage)
    } finally {
      setLoading(false)
    }
  }

  if (!packageData) {
    return <div className="text-center py-12">Loading...</div>
  }

  const totalParticipants = formData.adults + formData.children
  const totalPrice = (packageData.discount_price || packageData.price) * totalParticipants
  const tax = totalPrice * 0.1
  const finalTotal = totalPrice + tax

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold mb-8">Complete Your Booking</h1>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2">
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="card">
              <h2 className="text-xl font-semibold mb-4">Travel Details</h2>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Travel Date
                  </label>
                  <input
                    type="date"
                    required
                    min={new Date().toISOString().split('T')[0]}
                    value={formData.travel_date}
                    onChange={(e) => setFormData({ ...formData, travel_date: e.target.value })}
                    className="input"
                  />
                </div>
                <div className="grid grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Adults
                    </label>
                    <input
                      type="number"
                      min="1"
                      required
                      value={formData.adults}
                      onChange={(e) => setFormData({ ...formData, adults: parseInt(e.target.value) })}
                      className="input"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Children
                    </label>
                    <input
                      type="number"
                      min="0"
                      value={formData.children}
                      onChange={(e) => setFormData({ ...formData, children: parseInt(e.target.value) })}
                      className="input"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Infants
                    </label>
                    <input
                      type="number"
                      min="0"
                      value={formData.infants}
                      onChange={(e) => setFormData({ ...formData, infants: parseInt(e.target.value) })}
                      className="input"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="card">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-semibold">Traveler Information</h2>
                <button type="button" onClick={addTraveler} className="btn btn-secondary">
                  Add Traveler
                </button>
              </div>
              <div className="space-y-4">
                {formData.travelers_info.map((traveler, index) => (
                  <div key={index} className="border p-4 rounded-lg">
                    <h3 className="font-semibold mb-3">Traveler {index + 1}</h3>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Full Name *
                        </label>
                        <input
                          type="text"
                          required
                          value={traveler.name}
                          onChange={(e) => handleTravelerChange(index, 'name', e.target.value)}
                          className="input"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Email *
                        </label>
                        <input
                          type="email"
                          required
                          value={traveler.email}
                          onChange={(e) => handleTravelerChange(index, 'email', e.target.value)}
                          className="input"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Phone *
                        </label>
                        <input
                          type="tel"
                          required
                          value={traveler.phone}
                          onChange={(e) => handleTravelerChange(index, 'phone', e.target.value)}
                          className="input"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Passport Number
                        </label>
                        <input
                          type="text"
                          value={traveler.passport}
                          onChange={(e) => handleTravelerChange(index, 'passport', e.target.value)}
                          className="input"
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="card">
              <h2 className="text-xl font-semibold mb-4">Special Requests</h2>
              <textarea
                value={formData.special_requests}
                onChange={(e) => setFormData({ ...formData, special_requests: e.target.value })}
                rows="4"
                className="input"
                placeholder="Any special requests or requirements..."
              />
            </div>

            <button type="submit" disabled={loading} className="btn btn-primary w-full text-lg py-3">
              {loading ? 'Processing...' : 'Confirm Booking'}
            </button>
          </form>
        </div>

        <div>
          <div className="card sticky top-4">
            <h2 className="text-xl font-semibold mb-4">Booking Summary</h2>
            <div className="space-y-2 mb-4">
              <p className="text-gray-600">{packageData.title}</p>
              <p className="text-sm text-gray-500">{packageData.destination}</p>
            </div>
            <div className="border-t pt-4 space-y-2">
              <div className="flex justify-between">
                <span>Price ({totalParticipants} {totalParticipants === 1 ? 'person' : 'people'})</span>
                <span>${totalPrice.toFixed(2)}</span>
              </div>
              <div className="flex justify-between">
                <span>Tax (10%)</span>
                <span>${tax.toFixed(2)}</span>
              </div>
              <div className="border-t pt-2 flex justify-between font-bold text-lg">
                <span>Total</span>
                <span>${finalTotal.toFixed(2)}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Booking

