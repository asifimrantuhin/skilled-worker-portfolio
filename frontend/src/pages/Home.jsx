import { Link } from 'react-router-dom'
import { useEffect, useState } from 'react'
import api from '../services/api'

const Home = () => {
  const [featuredPackages, setFeaturedPackages] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchFeaturedPackages()
  }, [])

  const fetchFeaturedPackages = async () => {
    try {
      const response = await api.get('/packages?featured=1&per_page=6')
      if (response.data.success && response.data.packages) {
        const packages = response.data.packages.data || response.data.packages
        setFeaturedPackages(Array.isArray(packages) ? packages : [])
      } else {
        setFeaturedPackages([])
      }
    } catch (error) {
      console.error('Error fetching packages:', error)
      setFeaturedPackages([])
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      {/* Hero Section */}
      <section className="bg-gradient-to-r from-primary-600 to-primary-800 text-white py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h1 className="text-5xl font-bold mb-4">Discover Amazing Destinations</h1>
          <p className="text-xl mb-8">Plan your perfect trip with our curated travel packages</p>
          <Link to="/packages" className="btn bg-white text-primary-600 hover:bg-gray-100 inline-block">
            Explore Packages
          </Link>
        </div>
      </section>

      {/* Featured Packages */}
      <section className="py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl font-bold text-center mb-12">Featured Packages</h2>
          {loading ? (
            <div className="text-center">Loading...</div>
          ) : featuredPackages.length === 0 ? (
            <div className="text-center py-12 text-gray-500">
              <p>No featured packages available at the moment.</p>
              <Link to="/packages" className="btn btn-primary mt-4 inline-block">
                Browse All Packages
              </Link>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {featuredPackages.map((pkg) => (
                <div key={pkg.id} className="card hover:shadow-lg transition-shadow">
                  {pkg.images && pkg.images[0] ? (
                    <img 
                      src={`http://localhost:8000/storage/${pkg.images[0]}`} 
                      alt={pkg.title}
                      className="w-full h-48 object-cover rounded-t-lg"
                      onError={(e) => {
                        e.target.src = 'https://via.placeholder.com/400x300?text=No+Image'
                      }}
                    />
                  ) : (
                    <div className="w-full h-48 bg-gray-200 rounded-t-lg flex items-center justify-center">
                      <span className="text-gray-400">No Image</span>
                    </div>
                  )}
                  <div className="p-4">
                    <h3 className="text-xl font-semibold mb-2">{pkg.title}</h3>
                    <p className="text-gray-600 mb-4 line-clamp-2">
                      {pkg.short_description || (pkg.description ? pkg.description.substring(0, 100) + '...' : 'No description available')}
                    </p>
                    <div className="flex justify-between items-center">
                      <span className="text-2xl font-bold text-primary-600">
                        ${pkg.discount_price || pkg.price || '0.00'}
                      </span>
                      <Link to={`/packages/${pkg.id}`} className="btn btn-primary">
                        View Details
                      </Link>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </section>
    </div>
  )
}

export default Home

