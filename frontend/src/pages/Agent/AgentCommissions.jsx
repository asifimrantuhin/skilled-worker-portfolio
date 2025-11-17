import { useState, useEffect } from 'react'
import api from '../services/api'

const AgentCommissions = () => {
  const [commissions, setCommissions] = useState([])
  const [summary, setSummary] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchCommissions()
  }, [])

  const fetchCommissions = async () => {
    try {
      const response = await api.get('/agent/commissions')
      if (response.data.success) {
        const commissions = response.data.commissions?.data || response.data.commissions || []
        setCommissions(Array.isArray(commissions) ? commissions : [])
        setSummary(response.data.summary || {})
      }
    } catch (error) {
      console.error('Error fetching commissions:', error)
      setCommissions([])
      setSummary({})
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return <div className="text-center py-12">Loading...</div>
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold mb-8">Commissions</h1>

      {summary && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div className="card">
            <h3 className="text-gray-600 mb-2">Total Commission</h3>
            <p className="text-3xl font-bold text-green-600">${summary.total_commission?.toFixed(2) || '0.00'}</p>
          </div>
          <div className="card">
            <h3 className="text-gray-600 mb-2">Pending Commission</h3>
            <p className="text-3xl font-bold text-yellow-600">${summary.pending_commission?.toFixed(2) || '0.00'}</p>
          </div>
          <div className="card">
            <h3 className="text-gray-600 mb-2">Total Bookings</h3>
            <p className="text-3xl font-bold text-primary-600">{summary.total_bookings || 0}</p>
          </div>
        </div>
      )}

      <div className="card">
        <h2 className="text-2xl font-semibold mb-4">Commission History</h2>
        {commissions.length === 0 ? (
          <p className="text-gray-500">No commissions found</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b">
                  <th className="text-left py-2">Booking</th>
                  <th className="text-left py-2">Amount</th>
                  <th className="text-left py-2">Rate</th>
                  <th className="text-left py-2">Commission</th>
                  <th className="text-left py-2">Status</th>
                </tr>
              </thead>
              <tbody>
                {commissions.map((commission) => (
                  <tr key={commission.id} className="border-b">
                    <td className="py-2">{commission.booking?.package?.title}</td>
                    <td className="py-2">${commission.booking_amount}</td>
                    <td className="py-2">{commission.commission_rate}%</td>
                    <td className="py-2 font-semibold">${commission.commission_amount}</td>
                    <td className="py-2">
                      <span className={`px-3 py-1 rounded-full text-sm ${
                        commission.status === 'paid' ? 'bg-green-100 text-green-800' :
                        commission.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {commission.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

export default AgentCommissions

