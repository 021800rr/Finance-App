import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { deleteResource, getJson, patchJson, postJson } from '../api'

// One gold holding of GET /api/gold_assets: its form (bar, coin…), weight and description.
export type GoldAsset = {
  id: number
  formId: number
  formName: string
  description: string
  // a decimal string with four places, e.g. '1.5000'
  ounces: string
}

export type GoldForm = { id: number; name: string }

// What the form sends; the API asks for whatever is missing.
export type GoldAssetInput = { formId: number | null; ounces: string; description: string }

// Every gold asset, by id, as in the web app.
export function useGoldAssets() {
  return useQuery({ queryKey: ['goldAssets'], queryFn: () => getJson<GoldAsset[]>('/api/gold_assets') })
}

export function useGoldAsset(id: string) {
  return useQuery({
    queryKey: ['goldAssets', id],
    queryFn: () => getJson<GoldAsset>(`/api/gold_assets/${encodeURIComponent(id)}`),
  })
}

export function useGoldForms() {
  return useQuery({ queryKey: ['goldForms'], queryFn: () => getJson<GoldForm[]>('/api/gold_forms') })
}

// After a change the assets are read again, and the gold prices too: they name the assets, and a deleted asset takes
// its prices with it.
function useInvalidateAfterChange() {
  const queryClient = useQueryClient()

  return () => Promise.all(
    [['goldAssets'], ['goldPrices']].map((queryKey) => queryClient.invalidateQueries({ queryKey })),
  )
}

export function useCreateGoldAsset() {
  const invalidate = useInvalidateAfterChange()

  return useMutation({
    mutationFn: (input: GoldAssetInput) => postJson<GoldAsset>('/api/gold_assets', input),
    onSuccess: invalidate,
  })
}

export function useUpdateGoldAsset(id: number) {
  const invalidate = useInvalidateAfterChange()

  return useMutation({
    mutationFn: (input: GoldAssetInput) => patchJson(`/api/gold_assets/${id}`, input),
    onSuccess: invalidate,
  })
}

export function useDeleteGoldAsset() {
  const invalidate = useInvalidateAfterChange()

  return useMutation({
    mutationFn: (id: number) => deleteResource(`/api/gold_assets/${id}`),
    onSettled: invalidate,
  })
}
