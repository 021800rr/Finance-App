import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'
import './index.css'
// Before App: every text it shows comes from the translations.
import './i18n'
import App from './App.tsx'
import { ApiError } from './api'
import { AuthProvider } from './auth/AuthProvider'

// Keeps what was read from the API, so a page opened again shows it at once and refreshes it in the background.
// One retry, for a passing network hiccup or a server error. An answer about the request itself (404, 422…) would
// only come again, and a 401 is handled by apiFetch.
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: (failures, error) => failures < 1 && !(error instanceof ApiError && error.status < 500),
    },
  },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <App />
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
)
