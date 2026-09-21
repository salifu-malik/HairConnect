import { useState } from 'react';
import { reviewService } from '../../../api/reviewService';
import { toast } from 'react-hot-toast';

interface ReviewFormProps {
  targetType: 'BARBER' | 'PRODUCT';
  targetId: string;
  onReviewSubmitted: () => void;
}

export const ReviewForm = ({ targetType, targetId, onReviewSubmitted }: ReviewFormProps) => {
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await reviewService.submitReview(targetType, targetId, rating, comment);
      toast.success('Review submitted successfully');
      setComment('');
      onReviewSubmitted();
    } catch {
      toast.error('Failed to submit review');
    }
  };

  return (
    <form onSubmit={handleSubmit} className="bg-white p-4 rounded shadow mt-4">
      <h4 className="font-semibold mb-2">Leave a Review</h4>
      <div className="mb-2">
        <label className="block text-sm">Rating (1-5)</label>
        <input
          type="number"
          min="1"
          max="5"
          value={rating}
          onChange={(e) => setRating(Number(e.target.value))}
          className="border p-2 rounded w-full"
        />
      </div>
      <div className="mb-2">
        <label className="block text-sm">Comment</label>
        <textarea
          value={comment}
          onChange={(e) => setComment(e.target.value)}
          className="border p-2 rounded w-full"
          rows={3}
        />
      </div>
      <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded">
        Submit
      </button>
    </form>
  );
};
