import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

const Packages = () => {
  const [packages, setPackages] = useState([])
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState({
    search: '',
    category: '',
    destination: '',
    min_price: '',
    max_price: '',
  })

  useEffect(() => {
    fetchPackages()
  }, [filters])

  const fetchPackages = async () => {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      Object.entries(filters).forEach(([key, value]) => {
        if (value) params.append(key, value)
      })
      const response = await api.get(`/packages?${params.toString()}`)
      if (response.data.success && response.data.packages) {
        const packages = response.data.packages.data || response.data.packages
        setPackages(Array.isArray(packages) ? packages : [])
      } else {
        setPackages([])
      }
    } catch (error) {
      console.error('Error fetching packages:', error)
      setPackages([])
    } finally {
      setLoading(false)
    }
  }

  const handleFilterChange = (e) => {
    setFilters({ ...filters, [e.target.name]: e.target.value })
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold mb-8">Tour Packages</h1>

      {/* Filters */}
      <div className="card mb-8">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <input
            type="text"
            name="search"
            placeholder="Search packages..."
            value={filters.search}
            onChange={handleFilterChange}
            className="input"
          />
          <input
            type="text"
            name="destination"
            placeholder="Destination"
            value={filters.destination}
            onChange={handleFilterChange}
            className="input"
          />
          <input
            type="number"
            name="min_price"
            placeholder="Min Price"
            value={filters.min_price}
            onChange={handleFilterChange}
            className="input"
          />
          <input
            type="number"
            name="max_price"
            placeholder="Max Price"
            value={filters.max_price}
            onChange={handleFilterChange}
            className="input"
          />
        </div>
      </div>

      {/* Packages Grid */}
      {loading ? (
        <div className="text-center">Loading...</div>
      ) : packages.length === 0 ? (
        <div className="card text-center py-12">
          <p className="text-gray-500 mb-4">No packages found matching your criteria.</p>
          <button 
            onClick={() => setFilters({ search: '', category: '', destination: '', min_price: '', max_price: '' })}
            className="btn btn-secondary"
          >
            Clear Filters
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {packages.map((pkg) => (
            <div key={pkg.id} className="card hover:shadow-lg transition-shadow">
              {pkg.images && pkg.images[0] ? (
                <img 
                  src={`http://localhost:8000/storage/${pkg.images[0]}`} 
                  alt={pkg.title}
                  className="w-full h-48 object-cover rounded-lg mb-4"
                  onError={(e) => {
                    e.target.src = 'https://via.placeholder.com/400x300?text=No+Image'
                  }}
                />
              ) : (
                <div className="w-full h-48 bg-gray-200 rounded-lg mb-4 flex items-center justify-center">
                  <span className="text-gray-400">No Image</span>
                </div>
              )}
              <h3 className="text-xl font-semibold mb-2">{pkg.title}</h3>
              <p className="text-gray-600 mb-4 line-clamp-2">
                {pkg.short_description || (pkg.description ? pkg.description.substring(0, 100) + '...' : 'No description')}
              </p>
              <div className="flex justify-between items-center">
                <div>
                  <span className="text-2xl font-bold text-primary-600">
                    ${pkg.discount_price || pkg.price || '0.00'}
                  </span>
                  {pkg.discount_price && (
                    <span className="text-gray-500 line-through ml-2">${pkg.price}</span>
                  )}
                </div>
                <Link to={`/packages/${pkg.id}`} className="btn btn-primary">
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

export default Packages

