import axios from 'axios'

export async function loadAdminDashboard() {
  const response = await axios.get('/admin/dashboard')
  return response.data.statistics
}

export async function listAdminUsers(params = {}) {
  const response = await axios.get('/admin/users', { params })
  return response.data.users
}

export async function setAdminUserStatus(userId, isActive) {
  const response = await axios.patch(`/admin/users/${userId}/status`, {
    is_active: isActive,
  })

  return response.data
}

export async function listAdminCrosswords(params = {}) {
  const response = await axios.get('/admin/crosswords', { params })
  return response.data.crosswords
}

export async function deleteAdminCrossword(crosswordId) {
  const response = await axios.delete(`/admin/crosswords/${crosswordId}`)
  return response.data
}

export async function listAdminClues(params = {}) {
  const response = await axios.get('/admin/clues', { params })
  return response.data.clues
}

export async function deleteAdminClue(clueId) {
  const response = await axios.delete(`/admin/clues/${clueId}`)
  return response.data
}