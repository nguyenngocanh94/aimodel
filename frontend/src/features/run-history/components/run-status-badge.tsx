import { Badge } from '@/shared/ui/badge'

const statusStyles: Record<string, string> = {
  success: 'bg-green-100 text-green-800 border-green-200',
  error: 'bg-red-100 text-red-800 border-red-200',
  running: 'bg-blue-100 text-blue-800 border-blue-200',
  pending: 'bg-gray-100 text-gray-600 border-gray-200',
  cancelled: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  awaitingReview: 'bg-orange-100 text-orange-800 border-orange-200',
  interrupted: 'bg-red-100 text-red-700 border-red-200',
}

export function RunStatusBadge({ status }: { readonly status: string }) {
  const style = statusStyles[status] ?? statusStyles.pending

  return (
    <Badge variant="outline" className={`text-[10px] font-medium ${style}`}>
      {status}
    </Badge>
  )
}
