import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { Toaster } from 'react-hot-toast'
import { AuthProvider } from './contexts/AuthContext'
import PrivateRoute from './components/PrivateRoute'
import Navbar from './components/Layout/Navbar'
import Footer from './components/Layout/Footer'

// Pages
import Home from './pages/Home'
import Packages from './pages/Packages'
import PackageDetail from './pages/PackageDetail'
import Booking from './pages/Booking'
import Login from './pages/Auth/Login'
import Register from './pages/Auth/Register'
import Dashboard from './pages/Dashboard'
import MyBookings from './pages/MyBookings'
import BookingDetail from './pages/BookingDetail'
import Inquiry from './pages/Inquiry'
import Tickets from './pages/Tickets'
import TicketDetail from './pages/TicketDetail'
import AgentDashboard from './pages/Agent/AgentDashboard'
import AgentBookings from './pages/Agent/AgentBookings'
import AgentCommissions from './pages/Agent/AgentCommissions'

function App() {
  return (
    <AuthProvider>
      <Router>
        <div className="min-h-screen flex flex-col">
          <Navbar />
          <main className="flex-grow">
            <Routes>
              <Route path="/" element={<Home />} />
              <Route path="/packages" element={<Packages />} />
              <Route path="/packages/:id" element={<PackageDetail />} />
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />
              <Route path="/inquiry" element={<Inquiry />} />
              
              {/* Protected Routes */}
              <Route path="/booking/:packageId" element={
                <PrivateRoute>
                  <Booking />
                </PrivateRoute>
              } />
              <Route path="/dashboard" element={
                <PrivateRoute>
                  <Dashboard />
                </PrivateRoute>
              } />
              <Route path="/bookings" element={
                <PrivateRoute>
                  <MyBookings />
                </PrivateRoute>
              } />
              <Route path="/bookings/:id" element={
                <PrivateRoute>
                  <BookingDetail />
                </PrivateRoute>
              } />
              <Route path="/tickets" element={
                <PrivateRoute>
                  <Tickets />
                </PrivateRoute>
              } />
              <Route path="/tickets/:id" element={
                <PrivateRoute>
                  <TicketDetail />
                </PrivateRoute>
              } />
              
              {/* Agent Routes */}
              <Route path="/agent/dashboard" element={
                <PrivateRoute allowedRoles={['agent', 'admin']}>
                  <AgentDashboard />
                </PrivateRoute>
              } />
              <Route path="/agent/bookings" element={
                <PrivateRoute allowedRoles={['agent', 'admin']}>
                  <AgentBookings />
                </PrivateRoute>
              } />
              <Route path="/agent/commissions" element={
                <PrivateRoute allowedRoles={['agent', 'admin']}>
                  <AgentCommissions />
                </PrivateRoute>
              } />
            </Routes>
          </main>
          <Footer />
          <Toaster position="top-right" />
        </div>
      </Router>
    </AuthProvider>
  )
}

export default App

