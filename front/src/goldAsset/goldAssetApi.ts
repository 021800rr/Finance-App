import { useMutation, useQuery } from '@tanstack/react-query'
import { deleteResource, getJson, patchJson, postJson } from '../api'
import type { components } from '../generated/api'
import { queryKeys, useInvalidate } from '../queryKeys'

// The types of the API, generated from its OpenAPI description (npm run api-types): a gold asset as the API answers
// it (its form, a bar or a coin, its weight in ounces with four places, its description) and the gold forms…
export type GoldAsset = components['schemas']['GoldAsset']
export type GoldForm = components['schemas']['GoldForm']

// …and what the form sends: any field may be missing or null, and the API says which one is wrong.
export type GoldAssetInput = components['schemas']['GoldAsset.GoldAssetInput']
export type GoldAssetChanges = components['schemas']['GoldAsset.GoldAssetInput.jsonMergePatch']

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
    mutationFn: (input: GoldAssetChanges) => patchJson(`/api/gold_assets/${id}`, input),
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
