import React, { useState, useRef, useEffect } from 'react';
import { postRequest, getRequest, deleteRequest, patchRequest } from '@/shared/lib/api-client';
import type { AllResponse } from '@/shared/types/common.types.ts';
import { XCircleIcon } from 'lucide-react';
import { toast } from 'sonner';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Locales } from '@/features/catalog/products/data/routes'
import { parseAndToastError } from '@/shared/lib/utils.ts'
import { type ApiError } from '@/shared/lib/api-error.ts'


interface Image {
  id: string
  file: File
  url: string
  is_main: boolean
}

export type ImageUploaderProps = {
  createEndpoint: string,
  updateEndpoint: string,
  deleteEndpoint: string,
  listEndpoint: string,
  variant_id?: string,
  collection?: string
}

export default function ImageUploader({createEndpoint, updateEndpoint, deleteEndpoint, listEndpoint, variant_id, collection}: ImageUploaderProps) {
  const inputRef = useRef<HTMLInputElement | null>(null)
  const { tMessage, tLabel: tcLabel } = useAppTranslation(Locales.SHARED_COMMON)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const [images, setImages] = useState<Image[]>([]);
  // const queryClient = useQueryClient()

  // const {data, isLoading, isSuccess} = useQuery<AllResponse<Image>>({
  //   queryKey: [ listEndpoint ],
  //   queryFn: () => getRequest(listEndpoint),
  //   // placeholderData: (prev) => prev,
  // });

  // if (isSuccess && !isLoading){
  //   setImages(data.data)
  //   console.log('data.data', data.data)
  // }

  useEffect(() => {
    const fetchData = async () => {
      // eslint-disable-next-line react-hooks/immutability
      await getItems();
    };
    fetchData();
  }, [])

  async function getItems() {
    const response: AllResponse<Image> = await getRequest(listEndpoint)
    setImages(response.data as Image[])
  }



  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    if (!e.target.files) return

    const file = e.target.files[0]
    if (!file) return

    // Upload the file
    const formData = new FormData()
    formData.append('file', file)
    formData.append('is_main', images.length === 0 ? '1': '0') // default false
    formData.append('sort_order', (images.length + 1).toString())
    if (variant_id) formData.append('variant_id', variant_id)
    if (collection) formData.append('collection', collection)


    try {
      await postRequest(createEndpoint, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })

      await getItems();

      toast.success(tMessage('success.upload_general'))

      // await queryClient.invalidateQueries({ queryKey: [listEndpoint] })
       
    } catch (err) {
      parseAndToastError(err as ApiError)
    }
  }

  const handleSelectFiles = () => {
    inputRef.current?.click()
  }

  const handleSetMain = async (img: Image) => {
    try {
      await patchRequest(updateEndpoint.replace('$id', img.id.toString()), {is_main: !img.is_main})
      await getItems();
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (err) {
      toast.error(tMessage('error.general'))
    }
  }

  const handleRemoveImage = async (id: string) => {
    try {
        if (confirm(t('dialog.delete.delete_confirmation'))) {
          await deleteRequest(deleteEndpoint.replace('$id', id))
          await getItems();
          toast.success(tMessage('success.record.deleted', {name: tcLabel('image')}))
        }
      // await queryClient.invalidateQueries({ queryKey: [listEndpoint] })
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (err) {
      toast.error(tMessage('error.general'))
    }
  }

  return (
    <div>
      <div className="label label">{tcLabel('images')}</div>


      {/* Upload box */}
      <div
        className="w-24 h-24 border-2 border-dashed flex items-center justify-center cursor-pointer"
        onClick={handleSelectFiles}
      >
        <span style={{ fontSize: 24 }}>+</span>
      </div>

      {/* Hidden input for file select */}
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        style={{ display: 'none' }}
        onChange={handleFileChange}
      />

      {/* Images display */}
      <div className="mt-4 overflow-x-auto flex space-x-4 p-2 border rounded" style={{ maxHeight: '150px' }}>
        {images.map((img) => (
          <div key={img.id} className="relative flex-shrink-0" style={{ width: 100, height: 100 }}>
            <img
              src={img.url}
              alt="uploaded"
              className={`w-full h-full object-cover border ${img.is_main ? 'border-blue-500' : 'border-gray-300'}`}
              onClick={() => handleSetMain(img)}
            />
            {img.is_main && (
              <div className="absolute top-1 right-1 bg-blue-900 border-2 text-white rounded px-1 text-xs">{tcLabel('main')}</div>
            )}
            {/* Remove icon */}
            <button
              className="absolute top-1 left-1"
              onClick={() => handleRemoveImage(img.id)}
            >
              <XCircleIcon className="w-4 h-4 text-red-500" />
            </button>
          </div>
        ))}
      </div>
    </div>
  )
}
