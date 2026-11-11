import { useMutation, useQuery } from '@tanstack/react-query'
import { deleteResource, getJson, patchJson, postJson } from '../api'
import { queryKeys, useInvalidate } from '../queryKeys'

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
  return useQuery({ queryKey: queryKeys.goldAssets(), queryFn: () => getJson<GoldAsset[]>('/api/gold_assets') })
}

export function useGoldAsset(id: string) {
  return useQuery({
    queryKey: queryKeys.goldAsset(id),
    queryFn: () => getJson<GoldAsset>(`/api/gold_assets/${encodeURIComponent(id)}`),
  })
}

export function useGoldForms() {
  return useQuery({ queryKey: queryKeys.goldForms(), queryFn: () => getJson<GoldForm[]>('/api/gold_forms') })
}

export function useCreateGoldAsset() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: GoldAssetInput) => postJson<GoldAsset>('/api/gold_assets', input),
    onSuccess: () => invalidate('gold'),
  })
}

export function useUpdateGoldAsset(id: number) {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: GoldAssetInput) => patchJson(`/api/gold_assets/${id}`, input),
    onSuccess: () => invalidate('gold'),
  })
}

export function useDeleteGoldAsset() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (id: number) => deleteResource(`/api/gold_assets/${id}`),
    onSettled: () => invalidate('gold'),
  })
}
