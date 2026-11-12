import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Route, Routes } from 'react-router'
import { useAuth } from './auth/AuthContext'
import { LoginForm } from './auth/LoginForm'
import { Layout } from './layout/Layout'
import { NotFoundPage } from './pages/NotFoundPage'
import { SummaryPage } from './summary/SummaryPage'

// A page loaded when it is first opened. React.lazy wants a module with a default export; the pages are exported
// under their names, so this picks the named one.
function lazyPage<Name extends string>(load: () => Promise<Record<Name, React.ComponentType>>, name: Name) {
  return React.lazy(async () => ({ default: (await load())[name] }))
}

// The home page comes with the app; every other page is loaded when it is first opened, with what only it needs
// (chart.js for the charts, the calendar for the forms), so the app starts with much less to download. Meanwhile
// Layout shows "Wczytuję…".
const AccountOperationsPage = lazyPage(() => import('./account/AccountOperationsPage'), 'AccountOperationsPage')
const NewOperationPage = lazyPage(() => import('./operation/NewOperationPage'), 'NewOperationPage')
const EditOperationPage = lazyPage(() => import('./operation/EditOperationPage'), 'EditOperationPage')
const TransferPage = lazyPage(() => import('./transfer/TransferPage'), 'TransferPage')
const SearchPage = lazyPage(() => import('./search/SearchPage'), 'SearchPage')
const FixedExpensesPage = lazyPage(() => import('./fixedExpense/FixedExpensesPage'), 'FixedExpensesPage')
const EditFixedExpensePage = lazyPage(() => import('./fixedExpense/EditFixedExpensePage'), 'EditFixedExpensePage')
const RatesPage = lazyPage(() => import('./rate/RatesPage'), 'RatesPage')
const GoldAssetsPage = lazyPage(() => import('./goldAsset/GoldAssetsPage'), 'GoldAssetsPage')
const EditGoldAssetPage = lazyPage(() => import('./goldAsset/EditGoldAssetPage'), 'EditGoldAssetPage')
const GoldPricesPage = lazyPage(() => import('./goldPrice/GoldPricesPage'), 'GoldPricesPage')

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
