import { useLocation, useNavigate } from 'react-router'

// "Anuluj": back where the user came from. The first page of a browser tab (opened from a bookmark or a typed address)
// has nowhere to go back to inside the app; React Router gives it the key "default", and the fallback is used then
// instead of leaving the app.
export function useGoBack(fallback: string): () => void {
  const navigate = useNavigate()
  const location = useLocation()

  return () => {
    if (location.key === 'default') {
      void navigate(fallback)
    } else {
      void navigate(-1)
    }
  }
}
