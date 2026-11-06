import { Route, Routes } from 'react-router'
import { useAuth } from './auth/AuthContext'
import { LoginForm } from './auth/LoginForm'
import { Layout } from './layout/Layout'
import { AccountOperationsPage } from './account/AccountOperationsPage'
import { EditOperationPage } from './operation/EditOperationPage'
import { NewOperationPage } from './operation/NewOperationPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { PagePlaceholder } from './pages/PagePlaceholder'
import { SummaryPage } from './summary/SummaryPage'

// The login form until the user is logged in, whatever the address; afterwards the page of the address, so a link
// opened while logged out lands where it pointed.
function App() {
  const { checking, email } = useAuth()

  if (checking) {
    return <p className="p-8 text-center text-slate-500">Sprawdzam logowanie…</p>
  }
  if (email === null) {
    return <LoginForm />
  }

  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<SummaryPage />} />
        <Route path="account/:id/operations" element={<AccountOperationsPage />} />
        <Route path="operation/new" element={<NewOperationPage />} />
        <Route path="operation/:id/edit" element={<EditOperationPage />} />
        <Route path="transfer/new" element={<PagePlaceholder title="Przelew / Wymiana" />} />
        <Route path="fixed-expense" element={<PagePlaceholder title="Opłaty stałe" />} />
        <Route path="rate" element={<PagePlaceholder title="Kursy" />} />
        <Route path="gold-assets" element={<PagePlaceholder title="Złoto – aktywa" />} />
        <Route path="gold-prices" element={<PagePlaceholder title="Złoto – ceny" />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}

export default App
