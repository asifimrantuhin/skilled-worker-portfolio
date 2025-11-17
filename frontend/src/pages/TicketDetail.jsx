import { useState, useEffect } from 'react'
import { useParams } from 'react-router-dom'
import api from '../services/api'
import toast from 'react-hot-toast'
import { useAuth } from '../contexts/AuthContext'

const TicketDetail = () => {
  const { id } = useParams()
  const { user } = useAuth()
  const [ticket, setTicket] = useState(null)
  const [reply, setReply] = useState('')
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    fetchTicket()
  }, [id])

  const fetchTicket = async () => {
    try {
      const response = await api.get(`/tickets/${id}`)
      if (response.data.success && response.data.ticket) {
        setTicket(response.data.ticket)
      } else {
        toast.error('Ticket not found')
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Failed to load ticket')
    } finally {
      setLoading(false)
    }
  }

  const handleReply = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    try {
      await api.post(`/tickets/${id}/replies`, { message: reply })
      toast.success('Reply added successfully')
      setReply('')
      fetchTicket()
    } catch (error) {
      toast.error('Failed to add reply')
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return <div className="text-center py-12">Loading...</div>
  }

  if (!ticket) {
    return <div className="text-center py-12">Ticket not found</div>
  }

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="card mb-6">
        <div className="flex justify-between items-start mb-4">
          <div>
            <h1 className="text-3xl font-bold mb-2">{ticket.subject}</h1>
            <p className="text-gray-600">Ticket #: {ticket.ticket_number}</p>
          </div>
          <span className={`px-4 py-2 rounded-full font-medium ${
            ticket.status === 'open' ? 'bg-blue-100 text-blue-800' :
            ticket.status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' :
            ticket.status === 'resolved' ? 'bg-green-100 text-green-800' :
            'bg-gray-100 text-gray-800'
          }`}>
            {ticket.status}
          </span>
        </div>
        <p className="text-gray-700">{ticket.description}</p>
      </div>

      <div className="card mb-6">
        <h2 className="text-xl font-semibold mb-4">Replies</h2>
        <div className="space-y-4">
          {ticket.replies && ticket.replies.length > 0 ? (
            ticket.replies.map((reply) => (
              <div key={reply.id} className="border p-4 rounded-lg">
                <div className="flex justify-between items-start mb-2">
                  <div>
                    <p className="font-semibold">{reply.user?.name}</p>
                    <p className="text-sm text-gray-500">
                      {new Date(reply.created_at).toLocaleString()}
                    </p>
                  </div>
                </div>
                <p className="text-gray-700">{reply.message}</p>
              </div>
            ))
          ) : (
            <p className="text-gray-500">No replies yet</p>
          )}
        </div>
      </div>

      {ticket.status !== 'closed' && (
        <div className="card">
          <h2 className="text-xl font-semibold mb-4">Add Reply</h2>
          <form onSubmit={handleReply}>
            <textarea
              value={reply}
              onChange={(e) => setReply(e.target.value)}
              rows="4"
              required
              className="input mb-4"
              placeholder="Type your reply..."
            />
            <button type="submit" disabled={submitting} className="btn btn-primary">
              {submitting ? 'Submitting...' : 'Submit Reply'}
            </button>
          </form>
        </div>
      )}
    </div>
  )
}

export default TicketDetail

