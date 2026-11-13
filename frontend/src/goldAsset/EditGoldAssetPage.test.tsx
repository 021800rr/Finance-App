import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { type Answer, fakeApi, sentJson } from '../test/fakeApi'
import { formAmount } from '../ui/form'
import { EditGoldAssetPage } from './EditGoldAssetPage'

const ASSET = { id: 3, formId: 1, formName: 'moneta', description: 'Krugerrand 1 oz', ounces: '1.0000' }

// The API of the page: the asset and the gold forms.
function serve(answer?: Answer) {
  return fakeApi({
    '/api/gold_assets/3': ASSET, '/api/gold_forms': [{ id: 1, name: 'moneta' }, { id: 2, name: 'sztabka' }],
  }, answer)
}

// The edit of asset 3; the list of the assets stands for where the page goes back to.
function showPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/gold-assets/3/edit']}>
        <Routes>
          <Route path="gold-assets/:id/edit" element={<EditGoldAssetPage />} />
          <Route path="gold-assets" element={<p>List of the assets</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

afterEach(() => { vi.unstubAllGlobals() })

describe('EditGoldAssetPage', () => {
  it('fills the form with the asset', async () => {
    serve()
    showPage()

    expect(await screen.findByLabelText(i18n.t('gold_asset.shape'))).toHaveDisplayValue('moneta')
    expect(screen.getByLabelText(i18n.t('gold_asset.ounces'))).toHaveValue(formAmount('1.0000'))
    expect(screen.getByLabelText(i18n.t('form.description'))).toHaveValue('Krugerrand 1 oz')
  })

  it('saves the changes and goes back to the list', async () => {
    const user = userEvent.setup()
    const fetch = serve((method, path) => method === 'PATCH' && path === '/api/gold_assets/3'
      ? new Response(null, { status: 204 })
      : undefined)
    showPage()
    await user.selectOptions(await screen.findByLabelText(i18n.t('gold_asset.shape')), 'sztabka')
    await user.type(screen.getByLabelText(i18n.t('form.description')), ', 2020')

    await user.click(screen.getByRole('button', { name: i18n.t('form.save') }))

    expect(await screen.findByText('List of the assets')).toBeInTheDocument()
    expect(sentJson(fetch, 'PATCH')).toEqual({ formId: 2, ounces: '1.0000', description: 'Krugerrand 1 oz, 2020' })
  })

  it('stays on the form when the changes are refused', async () => {
    const user = userEvent.setup()
    serve((method) => method === 'PATCH'
      ? Response.json({
        errorCode: 'validation.failed', violations: [
          { propertyPath: 'description', message: 'Refused.', code: 'gold_asset.description_not_blank', params: {} },
        ],
      }, { status: 422 })
      : undefined)
    showPage()
    await user.clear(await screen.findByLabelText(i18n.t('form.description')))

    await user.click(screen.getByRole('button', { name: i18n.t('form.save') }))

    const description = screen.getByLabelText(i18n.t('form.description'))
    await waitFor(() => {
      expect(description).toHaveAccessibleDescription(i18n.t('gold_asset.description_not_blank', { ns: 'validators' }))
    })
    expect(screen.queryByText('List of the assets')).not.toBeInTheDocument()
  })

  it('goes back to the list without saving', async () => {
    const user = userEvent.setup()
    const fetch = serve()
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('form.cancel') }))

    expect(await screen.findByText('List of the assets')).toBeInTheDocument()
    expect(fetch).not.toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ method: 'PATCH' }))
  })

  it('shows the page of a missing address for an asset that does not exist', async () => {
    serve((method, path) => method === 'GET' && path === '/api/gold_assets/3'
      ? Response.json({ errorCode: 'gold_asset.not_found' }, { status: 404 })
      : undefined)
    showPage()

    expect(await screen.findByText(i18n.t('error.not_found_title'))).toBeInTheDocument()
  })
})
