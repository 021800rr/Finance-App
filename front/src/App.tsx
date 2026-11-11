import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Route, Routes } from 'react-router'
import { useAuth } from './auth/AuthContext'
import { LoginForm } from './auth/LoginForm'
import { Layout } from './layout/Layout'
import { AccountOperationsPage } from './account/AccountOperationsPage'
import { EditFixedExpensePage } from './fixedExpense/EditFixedExpensePage'
import { FixedExpensesPage } from './fixedExpense/FixedExpensesPage'
import { EditGoldAssetPage } from './goldAsset/EditGoldAssetPage'
import { GoldAssetsPage } from './goldAsset/GoldAssetsPage'
import { EditOperationPage } from './operation/EditOperationPage'
import { NewOperationPage } from './operation/NewOperationPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { SearchPage } from './search/SearchPage'
import { TransferPage } from './transfer/TransferPage'
import { SummaryPage } from './summary/SummaryPage'

// The two pages with a chart are loaded when first opened: chart.js comes with them, and the rest of the app starts
// without it.
const RatesPage = React.lazy(async () => ({
  default: (await import('./rate/RatesPage')).RatesPage,
}))
const GoldPricesPage = React.lazy(async () => ({
  default: (await import('./goldPrice/GoldPricesPage')).GoldPricesPage,
}))

// The login form until the user is logged in, whatever the address; afterwards the page of the address, so a link
// opened while logged out lands where it pointed.
function App() {
  const { t } = useTranslation()
  const { checking, email } = useAuth()

  if (checking) {
    return <p className="p-8 text-center text-slate-500 dark:text-slate-400">{t('app.checking_login')}</p>
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
        <Route path="transfer/new" element={<TransferPage />} />
        <Route path="search" element={<SearchPage />} />
        <Route path="fixed-expense" element={<FixedExpensesPage />} />
        <Route path="fixed-expense/:id/edit" element={<EditFixedExpensePage />} />
        <Route path="rate" element={<RatesPage />} />
        <Route path="gold-assets" element={<GoldAssetsPage />} />
        <Route path="gold-assets/:id/edit" element={<EditGoldAssetPage />} />
        <Route path="gold-prices" element={<GoldPricesPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}

export default App
