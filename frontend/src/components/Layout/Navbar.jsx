import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'

const Navbar = () => {
  const { isAuthenticated, user, logout, isAgent, isAdmin } = useAuth()
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/')
  }

  return (
    <nav className="bg-white shadow-md">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          <div className="flex">
            <Link to="/" className="flex items-center">
              <span className="text-2xl font-bold text-primary-600">Travel Agency</span>
            </Link>
            <div className="hidden sm:ml-6 sm:flex sm:space-x-8">
              <Link to="/packages" className="inline-flex items-center px-1 pt-1 text-gray-900 hover:text-primary-600">
                Packages
              </Link>
              {isAuthenticated && (
                <>
                  <Link to="/bookings" className="inline-flex items-center px-1 pt-1 text-gray-900 hover:text-primary-600">
                    My Bookings
                  </Link>
                  <Link to="/tickets" className="inline-flex items-center px-1 pt-1 text-gray-900 hover:text-primary-600">
                    Support
                  </Link>
                </>
              )}
              {(isAgent || isAdmin) && (
                <Link to="/agent/dashboard" className="inline-flex items-center px-1 pt-1 text-gray-900 hover:text-primary-600">
                  Agent Panel
                </Link>
              )}
            </div>
          </div>
          <div className="flex items-center">
            {isAuthenticated ? (
              <div className="flex items-center space-x-4">
                <span className="text-gray-700">{user?.name}</span>
                <button onClick={handleLogout} className="btn btn-secondary">
                  Logout
                </button>
              </div>
            ) : (
              <div className="flex items-center space-x-4">
                <Link to="/login" className="btn btn-secondary">
                  Login
                </Link>
                <Link to="/register" className="btn btn-primary">
                  Register
                </Link>
              </div>
            )}
          </div>
        </div>
      </div>
    </nav>
  )
}

export default Navbar

