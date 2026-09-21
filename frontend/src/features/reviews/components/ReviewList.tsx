interface Review {
  id: string;
  userName: string;
  rating: number;
  comment: string;
  date: string;
}

interface ReviewListProps {
  reviews: Review[];
}

export const ReviewList = ({ reviews }: ReviewListProps) => {
  return (
    <div className="mt-6">
      <h4 className="font-semibold mb-4">Reviews</h4>
      {reviews.length === 0 ? (
        <p className="text-gray-500">No reviews yet.</p>
      ) : (
        <div className="space-y-4">
          {reviews.map((review) => (
            <div key={review.id} className="bg-white p-4 rounded shadow">
              <div className="flex justify-between items-center mb-2">
                <span className="font-semibold">{review.userName}</span>
                <span className="text-yellow-500">{'★'.repeat(review.rating)}</span>
              </div>
              <p className="text-gray-600">{review.comment}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
